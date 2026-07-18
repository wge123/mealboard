<?php

use App\Actions\Planning\BuildShoppingList;
use App\Enums\IngredientCategory;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;

/**
 * Attach a one-recipe planned meal to the plan. Each call uses a fresh
 * date/slot pair so the plan/date/slot unique index is never violated.
 *
 * @param  list<array{0: Ingredient, 1: float|null, 2: string|null, 3?: string|null}>  $lines
 */
function attachMeal(MealPlan $plan, array $lines): void
{
    static $offset = 0;

    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);

    foreach ($lines as $line) {
        [$ingredient, $qty, $unit] = $line;

        $recipe->ingredients()->attach($ingredient->id, [
            'qty' => $qty,
            'unit' => $unit,
            'note' => $line[3] ?? null,
        ]);
    }

    $slots = MealSlot::cases();

    $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => $plan->week_start_date->copy()->addDays(intdiv($offset, count($slots)))->toDateString(),
        'slot' => $slots[$offset % count($slots)],
    ]);

    $offset++;
}

function shoppingList(MealPlan $plan, bool $includeStaples = false): array
{
    return app(BuildShoppingList::class)->handle($plan, $includeStaples);
}

/** Flatten the grouped list to one row per line for easy assertions. */
function flatLines(array $list): array
{
    return collect($list)->flatMap(fn (array $items) => $items)->values()->all();
}

it('refuses to build a list for a draft plan', function () {
    $plan = MealPlan::factory()->create();

    expect(fn () => shoppingList($plan))
        ->toThrow(LogicException::class, 'Only locked weeks have a shopping list.');
});

it('sums the same ingredient and unit across the week', function () {
    $plan = MealPlan::factory()->locked()->create();
    $chicken = Ingredient::factory()->create(['name' => 'chicken', 'category' => IngredientCategory::Meat]);

    attachMeal($plan, [[$chicken, 200, 'g']]);
    attachMeal($plan, [[$chicken, 300, 'g']]);

    expect(flatLines(shoppingList($plan)))->toBe([
        ['name' => 'chicken', 'qty' => 500.0, 'unit' => 'g', 'notes' => []],
    ]);
});

it('converts within the tsp/tbsp/cup family and picks a sensible display unit', function () {
    $plan = MealPlan::factory()->locked()->create();
    $cumin = Ingredient::factory()->create(['name' => 'cumin', 'category' => IngredientCategory::Pantry]);
    $butter = Ingredient::factory()->create(['name' => 'butter', 'category' => IngredientCategory::Dairy]);

    attachMeal($plan, [[$cumin, 1, 'tbsp'], [$butter, 8, 'tbsp']]);
    attachMeal($plan, [[$cumin, 3, 'tsp'], [$butter, 8, 'tbsp']]);

    // 1 tbsp + 3 tsp = 6 tsp = 2 tbsp; 8 tbsp + 8 tbsp = 16 tbsp = 1 cup.
    expect(flatLines(shoppingList($plan)))->toBe([
        ['name' => 'butter', 'qty' => 1.0, 'unit' => 'cup', 'notes' => []],
        ['name' => 'cumin', 'qty' => 2.0, 'unit' => 'tbsp', 'notes' => []],
    ]);
});

it('converts g/kg, displaying kg once the total reaches a kilogram', function () {
    $plan = MealPlan::factory()->locked()->create();
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);

    attachMeal($plan, [[$flour, 500, 'g']]);
    attachMeal($plan, [[$flour, 1, 'kg']]);

    expect(flatLines(shoppingList($plan)))->toBe([
        ['name' => 'flour', 'qty' => 1.5, 'unit' => 'kg', 'notes' => []],
    ]);
});

it('converts ml/l, keeping ml while the total stays under a liter', function () {
    $plan = MealPlan::factory()->locked()->create();
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);
    $stock = Ingredient::factory()->create(['name' => 'stock', 'category' => IngredientCategory::Pantry]);

    attachMeal($plan, [[$milk, 250, 'ml'], [$stock, 600, 'ml']]);
    attachMeal($plan, [[$milk, 250, 'ml'], [$stock, 500, 'ml']]);

    expect(flatLines(shoppingList($plan)))->toBe([
        ['name' => 'milk', 'qty' => 500.0, 'unit' => 'ml', 'notes' => []],
        ['name' => 'stock', 'qty' => 1.1, 'unit' => 'l', 'notes' => []],
    ]);
});

it('keeps mixed-family units of the same ingredient as separate lines', function () {
    $plan = MealPlan::factory()->locked()->create();
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);

    attachMeal($plan, [[$flour, 2, 'cup']]);
    attachMeal($plan, [[$flour, 500, 'g']]);

    $lines = flatLines(shoppingList($plan));

    expect($lines)->toHaveCount(2)
        ->and(collect($lines)->pluck('unit')->sort()->values()->all())->toBe(['cup', 'g']);
});

it('excludes pantry staples by default and includes them with the flag', function () {
    $plan = MealPlan::factory()->locked()->create();
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $chicken = Ingredient::factory()->create(['name' => 'chicken', 'category' => IngredientCategory::Meat]);

    attachMeal($plan, [[$salt, 1, 'tsp'], [$chicken, 500, 'g']]);

    expect(collect(flatLines(shoppingList($plan)))->pluck('name')->all())->toBe(['chicken'])
        ->and(collect(flatLines(shoppingList($plan, includeStaples: true)))->pluck('name')->sort()->values()->all())
        ->toBe(['chicken', 'salt']);
});

it('collects distinct pivot notes per merged line', function () {
    $plan = MealPlan::factory()->locked()->create();
    $onion = Ingredient::factory()->create(['name' => 'onion', 'category' => IngredientCategory::Produce]);

    attachMeal($plan, [[$onion, 1, 'count', 'diced']]);
    attachMeal($plan, [[$onion, 2, 'count', 'diced']]);
    attachMeal($plan, [[$onion, 1, 'count', 'sliced']]);

    expect(flatLines(shoppingList($plan)))->toBe([
        ['name' => 'onion', 'qty' => 4.0, 'unit' => 'count', 'notes' => ['diced', 'sliced']],
    ]);
});

it('groups by category in store order and sorts lines by name', function () {
    $plan = MealPlan::factory()->locked()->create();
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $carrot = Ingredient::factory()->create(['name' => 'carrot', 'category' => IngredientCategory::Produce]);
    $apple = Ingredient::factory()->create(['name' => 'apple', 'category' => IngredientCategory::Produce]);

    attachMeal($plan, [[$flour, 1, 'kg'], [$carrot, 3, 'count'], [$apple, 2, 'count']]);

    $list = shoppingList($plan);

    expect(array_keys($list))->toBe(['produce', 'pantry'])
        ->and(collect($list['produce'])->pluck('name')->all())->toBe(['apple', 'carrot']);
});

it('builds a list for a completed week too', function () {
    $plan = MealPlan::factory()->locked()->create(['status' => MealPlanStatus::Completed]);
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);

    attachMeal($plan, [[$milk, 1, 'l']]);

    expect(flatLines(shoppingList($plan)))->toHaveCount(1);
});
