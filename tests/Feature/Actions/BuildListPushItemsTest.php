<?php

use App\Actions\Planning\BuildListPushItems;
use App\Enums\IngredientCategory;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;

/**
 * Give the plan one planned meal whose recipe uses the named ingredients
 * (count units unless overridden), so the shopping list has one line each.
 *
 * @param  list<array{0: string, 1?: string|null}>  $ingredients  [name, unit]
 */
function pushListMeal(MealPlan $plan, array $ingredients, MealSlot $slot = MealSlot::Dinner): void
{
    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);

    foreach ($ingredients as $line) {
        $ingredient = Ingredient::query()->where('name', $line[0])->first()
            ?? Ingredient::factory()->create(['name' => $line[0], 'category' => IngredientCategory::Produce]);

        $recipe->ingredients()->attach($ingredient->id, ['qty' => 1, 'unit' => $line[1] ?? 'count']);
    }

    $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => $slot,
    ]);
}

function pushItems(?string $week = null): array
{
    return app(BuildListPushItems::class)->handle($week);
}

it('builds cleaned keywords from the latest locked week', function () {
    $older = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    pushListMeal($older, [['carrots']]);

    $latest = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushListMeal($latest, [['yellow onion'], ['chicken thighs']]);

    expect(pushItems())->toBe(['chicken thighs', 'yellow onion']);
});

it('skips checked items', function () {
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushListMeal($plan, [['yellow onion'], ['chicken thighs', 'lb']]);

    $plan->update(['checked_items' => ['chicken thighs|lb']]);

    expect(pushItems())->toBe(['yellow onion']);
});

it('reduces ingredient names to cleaned search keywords', function () {
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushListMeal($plan, [['diced yellow onion']]);

    expect(pushItems())->toBe(['yellow onion']);
});

it('dedupes an ingredient split across unmergeable unit lines', function () {
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushListMeal($plan, [['chicken', 'g']]);
    pushListMeal($plan, [['chicken', 'cup']], MealSlot::Lunch);

    expect(pushItems())->toBe(['chicken']);
});

it('targets an explicit week over the latest locked one', function () {
    $target = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    pushListMeal($target, [['carrots']]);

    $latest = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushListMeal($latest, [['yellow onion']]);

    expect(pushItems('2026-07-13'))->toBe(['carrots']);
});

it('fails loud when no week is locked', function () {
    MealPlan::factory()->create(); // draft only

    expect(fn () => pushItems())
        ->toThrow(RuntimeException::class, 'No locked week.');
});

it('fails loud when the explicit week has no plan', function () {
    expect(fn () => pushItems('2026-01-05'))
        ->toThrow(RuntimeException::class, 'No meal plan for week starting 2026-01-05.');
});

it('refuses a draft explicit week via the shopping-list guard', function () {
    MealPlan::factory()->create(['week_start_date' => '2026-07-20']);

    expect(fn () => pushItems('2026-07-20'))
        ->toThrow(LogicException::class, 'Only locked weeks have a shopping list.');
});
