<?php

use App\Actions\Planning\BuildShoppingList;
use App\Enums\IngredientCategory;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\WalmartMatch;
use Illuminate\Support\Arr;

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

/** Flatten the grouped lines to one row per line, keeping only the merge fields. */
function flatLines(array $list): array
{
    return collect($list['lines'])
        ->flatMap(fn (array $items) => $items)
        ->map(fn (array $line) => Arr::only($line, ['name', 'qty', 'unit', 'notes']))
        ->values()
        ->all();
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

    expect(array_keys($list['lines']))->toBe(['produce', 'pantry'])
        ->and(collect($list['lines']['produce'])->pluck('name')->all())->toBe(['apple', 'carrot']);
});

it('builds a list for a completed week too', function () {
    $plan = MealPlan::factory()->locked()->create(['status' => MealPlanStatus::Completed]);
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);

    attachMeal($plan, [[$milk, 1, 'l']]);

    expect(flatLines(shoppingList($plan)))->toHaveCount(1);
});

it('gives each line its key, label, checked state, product match and search link', function () {
    $plan = MealPlan::factory()->locked()->create();
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);
    $parsley = Ingredient::factory()->create(['name' => 'parsley', 'category' => IngredientCategory::Produce]);
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);

    attachMeal($plan, [[$onion, 2, 'count'], [$parsley, null, null], [$flour, 1.5, 'kg'], [$peas, 500, 'g']]);
    $plan->update(['checked_items' => ['flour|kg']]);

    expect(shoppingList($plan)['lines'])->toBe([
        'produce' => [
            [
                'key' => 'parsley|',
                'name' => 'parsley',
                'qty' => null,
                'unit' => null,
                'notes' => [],
                'label' => 'parsley',
                'checked' => false,
                'product_url' => null,
                'search_url' => 'https://www.walmart.com/search?q=parsley',
            ],
            [
                'key' => 'yellow onion|count',
                'name' => 'yellow onion',
                'qty' => 2.0,
                'unit' => 'count',
                'notes' => [],
                'label' => '2 yellow onion',
                'checked' => false,
                'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
                'search_url' => 'https://www.walmart.com/search?q=yellow+onion',
            ],
        ],
        'pantry' => [
            [
                'key' => 'flour|kg',
                'name' => 'flour',
                'qty' => 1.5,
                'unit' => 'kg',
                'notes' => [],
                'label' => '1.5 kg flour',
                'checked' => true,
                'product_url' => null,
                'search_url' => 'https://www.walmart.com/search?q=flour',
            ],
        ],
        'frozen' => [
            [
                'key' => 'frozen peas|g',
                'name' => 'frozen peas',
                'qty' => 500.0,
                'unit' => 'g',
                'notes' => [],
                'label' => '500 g frozen peas',
                'checked' => false,
                'product_url' => null,
                // "frozen" is a prep word: cleaning drops it from the search.
                'search_url' => 'https://www.walmart.com/search?q=peas',
            ],
        ],
    ]);
});

it('puts each ingredient on the buy list once, with cleaned keywords and its product match', function () {
    $plan = MealPlan::factory()->locked()->create();
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);

    // Cups and grams of flour stay two lines, but flour is one thing to buy.
    attachMeal($plan, [[$onion, 2, 'count'], [$flour, 2, 'cup'], [$peas, 500, 'g']]);
    attachMeal($plan, [[$flour, 500, 'g']]);

    expect(shoppingList($plan)['buy_list'])->toBe([
        ['name' => 'yellow onion', 'keywords' => 'yellow onion', 'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949'],
        ['name' => 'flour', 'keywords' => 'flour', 'product_url' => null],
        ['name' => 'frozen peas', 'keywords' => 'peas', 'product_url' => null],
    ]);
});

it('keeps an ingredient on the buy list until every one of its lines is checked', function () {
    $plan = MealPlan::factory()->locked()->create();
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);

    attachMeal($plan, [[$flour, 2, 'cup']]);
    attachMeal($plan, [[$flour, 500, 'g']]);

    $plan->update(['checked_items' => ['flour|cup']]);

    expect(collect(shoppingList($plan)['buy_list'])->pluck('name')->all())->toBe(['flour']);

    $plan->update(['checked_items' => ['flour|cup', 'flour|g']]);

    expect(shoppingList($plan)['buy_list'])->toBe([]);
});

it('never puts pantry staples on the buy list, even when they are shown', function () {
    $plan = MealPlan::factory()->locked()->create();
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $chicken = Ingredient::factory()->create(['name' => 'chicken', 'category' => IngredientCategory::Meat]);

    attachMeal($plan, [[$salt, 1, 'tsp'], [$chicken, 500, 'g']]);

    $list = shoppingList($plan, includeStaples: true);

    expect(collect(flatLines($list))->pluck('name')->sort()->values()->all())->toBe(['chicken', 'salt'])
        ->and(collect($list['buy_list'])->pluck('name')->all())->toBe(['chicken']);
});

it('builds the cart link from the buy list\'s matched products only', function () {
    $plan = MealPlan::factory()->locked()->create();
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);
    $chicken = Ingredient::factory()->create(['name' => 'chicken thighs', 'category' => IngredientCategory::Meat]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $chicken->id,
        'product_url' => 'https://www.walmart.com/ip/chicken-thighs/10315356',
    ]);
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    WalmartMatch::factory()->create([
        'ingredient_id' => $salt->id,
        'product_url' => 'https://www.walmart.com/ip/salt/10315357',
    ]);
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);

    // Onion is the only matched product still to buy: chicken is checked,
    // salt is a staple (shown, not bought), peas have no product match.
    attachMeal($plan, [[$onion, 2, 'count'], [$chicken, 500, 'g'], [$salt, 1, 'tsp'], [$peas, 500, 'g']]);
    $plan->update(['checked_items' => ['chicken thighs|g']]);

    expect(shoppingList($plan, includeStaples: true)['cart_link'])
        ->toBe('https://affil.walmart.com/cart/addToCart?items=44390949');
});

it('has no cart link when nothing on the buy list is matched', function () {
    $plan = MealPlan::factory()->locked()->create();
    $chicken = Ingredient::factory()->create(['name' => 'chicken thighs', 'category' => IngredientCategory::Meat]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $chicken->id,
        'product_url' => 'https://www.walmart.com/ip/chicken-thighs/10315356',
    ]);
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);

    attachMeal($plan, [[$chicken, 500, 'g'], [$peas, 500, 'g']]);
    $plan->update(['checked_items' => ['chicken thighs|g']]);

    expect(shoppingList($plan)['cart_link'])->toBeNull();
});
