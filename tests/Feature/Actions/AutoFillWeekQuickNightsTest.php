<?php

use App\Actions\Planning\AutoFillWeek;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Support\HouseholdPreferences;
use Random\Engine\Mt19937;
use Random\Randomizer;

// The factory's recipes need a skillet; the household owns it, so none is flagged.
beforeEach(fn () => KitchenToolKind::where('name', 'skillet')->update(['owned' => true]));

function quickNightFill(MealPlan $plan, int $seed = 1)
{
    return (new AutoFillWeek(new Randomizer(new Mt19937($seed))))->fill($plan);
}

/** A plan for the week of 2026-09-07 whose Tuesday (2026-09-08) is a quick night. */
function planWithQuickTuesday(array $extra = []): MealPlan
{
    return MealPlan::factory()->create([
        'week_start_date' => '2026-09-07',
        'check_in' => ['quick_nights' => ['2026-09-08'], ...$extra],
    ]);
}

function recipeTakingMinutes(int $prep, int $cook, array $attributes = []): Recipe
{
    return Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => $prep, 'cook_minutes' => $cook, ...$attributes]);
}

it('plans only a dinner within the quick-night limit on a quick night', function () {
    $quick = recipeTakingMinutes(5, 15); // 20 minutes, exactly the default limit
    recipeTakingMinutes(20, 30);
    recipeTakingMinutes(10, 15); // 25 minutes

    $plan = planWithQuickTuesday();

    quickNightFill($plan);

    $dinner = $plan->plannedMeals()->whereDate('date', '2026-09-08')->where('slot', 'dinner')->first();

    expect($dinner->recipe_id)->toBe($quick->id);
});

it('limits only dinner on a quick night', function () {
    recipeTakingMinutes(30, 30);

    $plan = planWithQuickTuesday();

    $result = quickNightFill($plan);

    $tuesday = $plan->plannedMeals()->whereDate('date', '2026-09-08')->pluck('slot')->map->value->sort()->values()->all();

    expect($tuesday)->toBe(['breakfast', 'lunch'])
        ->and($result->meals)->toHaveCount(14);
});

it('leaves a quick-night dinner empty and says why when no recipe fits', function () {
    recipeTakingMinutes(20, 40);

    $result = quickNightFill(planWithQuickTuesday());

    expect($result->unfilled)->toBe(['No recipe under 20 min for Tue dinner']);
});

it('follows the household quick-night limit', function () {
    recipeTakingMinutes(10, 10);
    app(HouseholdPreferences::class)->setQuickNightLimit(15);

    $plan = planWithQuickTuesday();
    $result = quickNightFill($plan);

    expect($result->unfilled)->toBe(['No recipe under 15 min for Tue dinner'])
        ->and($plan->plannedMeals()->whereDate('date', '2026-09-08')->where('slot', 'dinner')->exists())->toBeFalse();
});

it('reports nothing unfilled when no night is quick', function () {
    recipeTakingMinutes(20, 40);

    $result = quickNightFill(MealPlan::factory()->create(['week_start_date' => '2026-09-07']));

    expect($result->unfilled)->toBe([]);
});

it('says nothing about a quick night whose dinner is skipped', function () {
    recipeTakingMinutes(20, 40);

    $skipped = planWithQuickTuesday(['skipped' => [['date' => '2026-09-08', 'slot' => 'dinner']]]);

    expect(quickNightFill($skipped)->unfilled)->toBe([]);
});

it('says nothing about a quick night whose dinner is already planned', function () {
    recipeTakingMinutes(20, 40);

    $planned = planWithQuickTuesday();
    $planned->plannedMeals()->create(['recipe_id' => Recipe::first()->id, 'date' => '2026-09-08', 'slot' => MealSlot::Dinner]);

    expect(quickNightFill($planned)->unfilled)->toBe([]);
});

it('still leaves out avoided-ingredient and missing-tool recipes on a quick night', function () {
    app(HouseholdPreferences::class)->avoid('cilantro');

    $herby = recipeTakingMinutes(5, 5);
    $herby->ingredients()->attach(Ingredient::factory()->create(['name' => 'fresh cilantro'])->id, ['qty' => 1, 'unit' => null, 'note' => null]);

    KitchenToolKind::where('name', 'skillet')->update(['owned' => false]);
    recipeTakingMinutes(5, 5); // needs the skillet the household lacks

    $result = quickNightFill($plan = planWithQuickTuesday());

    expect($plan->plannedMeals()->whereDate('date', '2026-09-08')->where('slot', 'dinner')->exists())->toBeFalse()
        ->and($result->unfilled)->toBe(['No recipe under 20 min for Tue dinner']);
});
