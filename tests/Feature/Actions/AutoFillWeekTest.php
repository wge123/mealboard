<?php

use App\Actions\Planning\AutoFillWeek;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A far-future Monday so meal-log fixture dates (factory: now..+6d) never
 * fall inside the plan's prior-14-day penalty window.
 */
const AUTO_FILL_WEEK = '2026-09-07';

function autoFillAction(int $seed = 1): AutoFillWeek
{
    return new AutoFillWeek(new Randomizer(new Mt19937($seed)));
}

function autoFillPlan(string $weekStart = AUTO_FILL_WEEK): MealPlan
{
    return MealPlan::factory()->create(['week_start_date' => $weekStart]);
}

/** Attach rating logs to a recipe via planned meals in an unrelated plan. */
function autoFillRate(Recipe $recipe, int ...$ratings): void
{
    foreach ($ratings as $rating) {
        MealLog::factory()->create([
            'planned_meal_id' => PlannedMeal::factory()->create(['recipe_id' => $recipe->id])->id,
            'ate_it' => true,
            'rating' => $rating,
        ]);
    }
}

it('fills every empty weekday slot with a recipe matching the slot meal type or any', function () {
    Recipe::factory()->approved()->count(5)->create(['meal_type' => MealType::Breakfast]);
    Recipe::factory()->approved()->count(5)->create(['meal_type' => MealType::Lunch]);
    Recipe::factory()->approved()->count(5)->create(['meal_type' => MealType::Dinner]);
    Recipe::factory()->approved()->count(3)->create(['meal_type' => MealType::Any]);
    Recipe::factory()->count(3)->create(['meal_type' => MealType::Dinner]); // pending — never eligible

    $plan = autoFillPlan();

    $created = autoFillAction()->handle($plan);

    expect($created)->toHaveCount(15);

    $weekdays = collect(range(0, 4))
        ->map(fn (int $offset) => $plan->week_start_date->copy()->addDays($offset)->toDateString());

    foreach ($created as $meal) {
        expect($weekdays)->toContain($meal->date->toDateString())
            ->and($meal->recipe->status->value)->toBe('approved')
            ->and(in_array($meal->recipe->meal_type, [MealType::Any, MealType::from($meal->slot->value)], true))
            ->toBeTrue();
    }

    // Every weekday has exactly breakfast, lunch, dinner.
    $bySlotAndDay = $created->groupBy(fn (PlannedMeal $meal) => $meal->date->toDateString().'|'.$meal->slot->value);
    expect($bySlotAndDay)->toHaveCount(15);
});

it('prefers higher-rated recipes and drops the lowest-rated when the pool overflows', function () {
    $recipes = Recipe::factory()->approved()->count(6)->create(['meal_type' => MealType::Dinner]);

    $best = $recipes->first();
    $worst = $recipes->last();

    autoFillRate($best, 5, 5);
    autoFillRate($worst, 1, 1);

    $plan = autoFillPlan();

    $created = autoFillAction()->handle($plan);

    // Only dinner slots are fillable (no breakfast/lunch/any pool).
    expect($created)->toHaveCount(5);

    $mondayDinner = $created->first(fn (PlannedMeal $meal) => $meal->date->toDateString() === AUTO_FILL_WEEK
        && $meal->slot === MealSlot::Dinner);

    // Rated 5.0 beats every unrated (2.5) and rated-1 recipe, jitter included.
    expect($mondayDinner->recipe_id)->toBe($best->id)
        // 6 recipes for 5 slots: the 1.0-scored one is the one left out.
        ->and($created->pluck('recipe_id'))->not->toContain($worst->id);
});

it('penalizes recipes planned in the 14 days before the week start', function () {
    $recent = Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner]);
    $old = Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner]);

    // Both unrated. One planned 3 days before week start (inside the window),
    // the other 15 days before (outside the window).
    PlannedMeal::factory()->create(['recipe_id' => $recent->id, 'date' => '2026-09-04']);
    PlannedMeal::factory()->create(['recipe_id' => $old->id, 'date' => '2026-08-23']);

    $plan = autoFillPlan();

    $created = autoFillAction()->handle($plan);

    $mondayDinner = $created->first(fn (PlannedMeal $meal) => $meal->date->toDateString() === AUTO_FILL_WEEK
        && $meal->slot === MealSlot::Dinner);

    // 2.5 + jitter (old) beats 0.5 + jitter (recent).
    expect($mondayDinner->recipe_id)->toBe($old->id);
});

it('never repeats a recipe within the week when the pool is large enough', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);

    $created = autoFillAction()->handle(autoFillPlan());

    expect($created)->toHaveCount(15)
        ->and($created->pluck('recipe_id')->unique())->toHaveCount(15);
});

it('fills only empty slots and counts existing planned recipes toward the no-repeat rule', function () {
    $recipes = Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);

    $plan = autoFillPlan();

    $existing = $plan->plannedMeals()->create([
        'recipe_id' => $recipes->first()->id,
        'date' => AUTO_FILL_WEEK,
        'slot' => MealSlot::Breakfast,
    ]);

    $created = autoFillAction()->handle($plan);

    expect($created)->toHaveCount(14)
        ->and($created->pluck('id'))->not->toContain($existing->id)
        ->and($existing->fresh()->recipe_id)->toBe($recipes->first()->id);

    // Existing + created = 15 slots, all 15 recipes used exactly once.
    expect($plan->plannedMeals()->pluck('recipe_id')->unique())->toHaveCount(15);
});

it('reuses recipes evenly when the pool is smaller than the week', function () {
    Recipe::factory()->approved()->count(2)->create(['meal_type' => MealType::Any]);

    $created = autoFillAction()->handle(autoFillPlan());

    expect($created)->toHaveCount(15);

    $counts = $created->countBy('recipe_id');

    expect($counts)->toHaveCount(2)
        // Least-used-first fallback keeps reuse balanced (8/7 split).
        ->and($counts->max() - $counts->min())->toBeLessThanOrEqual(1);
});

it('refuses to fill a plan that is not a draft', function () {
    Recipe::factory()->approved()->count(3)->create(['meal_type' => MealType::Any]);

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => AUTO_FILL_WEEK]);

    expect(fn () => autoFillAction()->handle($plan))
        ->toThrow(LogicException::class, 'Only draft plans can be auto-filled.');

    expect($plan->plannedMeals()->count())->toBe(0);
});

it('boosts recipes matching profile-favored cuisines', function () {
    // Profile source: a LUNCH recipe with two 5-star logs makes 'thai'
    // favored, without competing in the dinner pool under test.
    $profiled = Recipe::factory()->approved()->create([
        'meal_type' => MealType::Lunch, 'cuisine' => 'thai', 'tags' => [],
    ]);
    autoFillRate($profiled, 5, 5);

    $thai = Recipe::factory()->approved()->create([
        'meal_type' => MealType::Dinner, 'cuisine' => 'thai', 'tags' => [],
    ]);
    $italian = Recipe::factory()->approved()->create([
        'meal_type' => MealType::Dinner, 'cuisine' => 'italian', 'tags' => [],
    ]);

    $created = autoFillAction()->handle(autoFillPlan());

    $mondayDinner = $created->first(fn (PlannedMeal $meal) => $meal->date->toDateString() === AUTO_FILL_WEEK
        && $meal->slot === MealSlot::Dinner);

    // Both candidates are unrated (2.5): the +0.5 cuisine boost beats the
    // 0.25 max jitter deterministically.
    expect($mondayDinner->recipe_id)->toBe($thai->id);
});

it('prefers faster breakfasts when the profile shows breakfasts are often skipped', function () {
    $fast = Recipe::factory()->approved()->create([
        'meal_type' => MealType::Breakfast, 'prep_minutes' => 5, 'cook_minutes' => 5,
        'cuisine' => null, 'tags' => [],
    ]);
    $slow = Recipe::factory()->approved()->create([
        'meal_type' => MealType::Breakfast, 'prep_minutes' => 30, 'cook_minutes' => 30,
        'cuisine' => null, 'tags' => [],
    ]);

    // Slow is better rated (3.0 via DINNER-slot logs, so breakfast skip
    // stats stay untouched): without the pattern it wins deterministically
    // (3.0 beats 2.5 + 0.25 max jitter).
    foreach ([3, 3] as $rating) {
        MealLog::factory()->create([
            'planned_meal_id' => PlannedMeal::factory()->create([
                'recipe_id' => $slow->id, 'slot' => MealSlot::Dinner,
            ])->id,
            'ate_it' => true,
            'rating' => $rating,
        ]);
    }

    $withoutPattern = autoFillAction(seed: 7)->handle(autoFillPlan('2026-09-07'));

    $mondayBreakfast = fn ($created, string $monday) => $created->first(
        fn (PlannedMeal $meal) => $meal->date->toDateString() === $monday && $meal->slot === MealSlot::Breakfast,
    );

    expect($mondayBreakfast($withoutPattern, '2026-09-07')->recipe_id)->toBe($slow->id);

    // Skip pattern: 2 of 3 logged breakfasts uneaten, attached to a LUNCH
    // recipe so the breakfast pool under test stays [fast, slow].
    $lunch = Recipe::factory()->approved()->create(['meal_type' => MealType::Lunch, 'cuisine' => null, 'tags' => []]);
    foreach ([false, false, true] as $ate) {
        MealLog::factory()->create([
            'planned_meal_id' => PlannedMeal::factory()->create([
                'recipe_id' => $lunch->id, 'slot' => MealSlot::Breakfast,
            ])->id,
            'ate_it' => $ate,
            'rating' => null,
        ]);
    }

    $withPattern = autoFillAction(seed: 7)->handle(autoFillPlan('2026-10-05'));

    // The per-minute penalty (0.02 * 60 = 1.2 vs 0.2) flips the pick to the
    // faster breakfast regardless of seed.
    expect($mondayBreakfast($withPattern, '2026-10-05')->recipe_id)->toBe($fast->id);
});

it('produces an identical fill for the same seed', function () {
    Recipe::factory()->approved()->count(10)->create(['meal_type' => MealType::Any]);

    $planA = autoFillPlan('2026-09-07');
    $planB = autoFillPlan('2026-10-05');

    $layout = fn ($created, MealPlan $plan) => $created
        ->mapWithKeys(fn (PlannedMeal $meal) => [
            $plan->week_start_date->diffInDays($meal->date).'|'.$meal->slot->value => $meal->recipe_id,
        ])
        ->all();

    $first = $layout(autoFillAction(seed: 42)->handle($planA), $planA);
    $second = $layout(autoFillAction(seed: 42)->handle($planB), $planB);

    expect($second)->toBe($first);
});
