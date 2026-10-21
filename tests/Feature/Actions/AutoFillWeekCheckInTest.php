<?php

use App\Actions\Planning\AutoFillWeek;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Support\HouseholdPreferences;
use Random\Engine\Mt19937;
use Random\Randomizer;

// The factory's recipes need a skillet; the household owns it, so none is flagged.
beforeEach(fn () => KitchenToolKind::where('name', 'skillet')->update(['owned' => true]));

function checkInFill(MealPlan $plan, int $seed = 1)
{
    return (new AutoFillWeek(new Randomizer(new Mt19937($seed))))->handle($plan);
}

/** A plan for the week of 2026-09-07 whose check-in skips the given date+slot pairs. */
function checkInPlanSkipping(array ...$pairs): MealPlan
{
    return MealPlan::factory()->create([
        'week_start_date' => '2026-09-07',
        'check_in' => ['skipped' => array_map(fn (array $pair) => ['date' => $pair[0], 'slot' => $pair[1]], $pairs)],
    ]);
}

it('never fills a skipped slot, however often it fills', function () {
    Recipe::factory()->approved()->count(5)->create(['meal_type' => MealType::Any]);

    $plan = checkInPlanSkipping(['2026-09-09', 'lunch'], ['2026-09-11', 'breakfast'], ['2026-09-11', 'lunch'], ['2026-09-11', 'dinner']);

    $first = checkInFill($plan);
    $second = checkInFill($plan, 2);

    expect($first)->toHaveCount(11)
        ->and($second)->toHaveCount(0)
        ->and($plan->plannedMeals()->whereDate('date', '2026-09-09')->where('slot', 'lunch')->exists())->toBeFalse()
        ->and($plan->plannedMeals()->whereDate('date', '2026-09-11')->exists())->toBeFalse();
});

it('fills a slot again once it is no longer skipped', function () {
    Recipe::factory()->approved()->count(5)->create(['meal_type' => MealType::Any]);

    $plan = checkInPlanSkipping(['2026-09-09', 'lunch']);
    checkInFill($plan);

    $plan->update(['check_in' => ['skipped' => []]]);
    $created = checkInFill($plan);

    expect($created)->toHaveCount(1)
        ->and($created->first()->slot)->toBe(MealSlot::Lunch);
});

it('fills the same seeded week with an empty check-in as with none', function () {
    Recipe::factory()->approved()->count(8)->create(['meal_type' => MealType::Any]);

    $bare = MealPlan::factory()->create(['week_start_date' => '2026-09-07']);
    $answered = MealPlan::factory()->create(['week_start_date' => '2026-09-14', 'check_in' => ['skipped' => []]]);

    $pick = fn (MealPlan $plan) => checkInFill($plan, 7)
        ->map(fn (PlannedMeal $meal) => $meal->slot->value.'|'.$meal->recipe_id)->all();

    expect($pick($answered))->toBe($pick($bare));
});

it('still leaves out missing-tool and avoided recipes when slots are skipped', function () {
    app(HouseholdPreferences::class)->avoid('cilantro');
    KitchenToolKind::where('name', 'skillet')->update(['owned' => false]);

    $herby = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);
    $herby->ingredients()->attach(Ingredient::factory()->create(['name' => 'fresh cilantro'])->id, ['qty' => 1, 'unit' => null, 'note' => null]);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Any]); // needs the skillet the household lacks
    $fine = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);
    $wok = KitchenToolKind::where('name', 'wok')->first();
    $wok->update(['owned' => true]);
    $fine->recipeTools->first()->alternatives()->update(['word' => 'wok', 'kitchen_tool_kind_id' => $wok->id]);

    $created = checkInFill(checkInPlanSkipping(['2026-09-07', 'dinner']));

    expect($created)->toHaveCount(14)
        ->and($created->pluck('recipe_id')->unique()->all())->toBe([$fine->id]);
});
