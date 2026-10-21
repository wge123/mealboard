<?php

use App\Actions\Planning\AutoFillWeek;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Support\HouseholdPreferences;
use Random\Engine\Mt19937;
use Random\Randomizer;

// The factory's recipes need a skillet; the household owns it, so none is flagged.
beforeEach(function () {
    KitchenToolKind::where('name', 'skillet')->update(['owned' => true]);

    // Plenty of other dinners, so normal filling never reaches for a craved cuisine.
    Recipe::factory()->approved()->count(20)->create(['meal_type' => MealType::Any, 'cuisine' => 'french'])
        ->each(fn (Recipe $recipe) => cravingRate($recipe, 4));
});

function cravingFill(MealPlan $plan)
{
    return (new AutoFillWeek(new Randomizer(new Mt19937(1))))->fill($plan);
}

function cravingPlan(array $checkIn): MealPlan
{
    return MealPlan::factory()->create(['week_start_date' => '2026-09-07', 'check_in' => $checkIn]);
}

function cravingRecipe(string $cuisine, int $minutes = 30, ?int $rating = null): Recipe
{
    $recipe = Recipe::factory()->approved()->create([
        'meal_type' => MealType::Dinner, 'cuisine' => $cuisine, 'prep_minutes' => 0, 'cook_minutes' => $minutes,
    ]);

    if ($rating !== null) {
        cravingRate($recipe, $rating);
    }

    return $recipe;
}

/** A rating log on a meal in an unrelated plan, far outside the recent-planning window. */
function cravingRate(Recipe $recipe, int $rating): void
{
    MealLog::factory()->create([
        'planned_meal_id' => PlannedMeal::factory()->create(['recipe_id' => $recipe->id, 'date' => '2026-01-05'])->id,
        'ate_it' => true,
        'rating' => $rating,
    ]);
}

/** Dinner recipe ids by date for the plan. */
function cravingDinners(MealPlan $plan): array
{
    return $plan->plannedMeals()->where('slot', 'dinner')->orderBy('date')->get()
        ->mapWithKeys(fn (PlannedMeal $meal) => [$meal->date->toDateString() => $meal->recipe_id])->all();
}

it('places exactly two dinners of the craved cuisine, from the best scoring recipes', function () {
    $best = cravingRecipe('thai', rating: 3);
    $second = cravingRecipe('thai', rating: 2);
    cravingRecipe('thai', rating: 1);

    $plan = cravingPlan(['craving' => 'thai']);

    $result = cravingFill($plan);

    $dinners = cravingDinners($plan);
    $thai = Recipe::where('cuisine', 'thai')->pluck('id');

    expect(collect($dinners)->filter(fn ($id) => $thai->contains($id)))->toHaveCount(2)
        ->and($dinners['2026-09-07'])->toBe($best->id)
        ->and($dinners['2026-09-08'])->toBe($second->id)
        ->and($result->unfilled)->toBe([]);
});

it('matches the craved cuisine trimmed and case-insensitively', function () {
    $a = cravingRecipe(' Thai ');
    $b = cravingRecipe('THAI');

    $plan = cravingPlan(['craving' => 'thai']);
    cravingFill($plan);

    expect(collect(cravingDinners($plan))->filter(fn ($id) => in_array($id, [$a->id, $b->id], true)))->toHaveCount(2);
});

it('puts craving dinners on non-quick nights first, earliest first', function () {
    $a = cravingRecipe('thai', rating: 3);
    $b = cravingRecipe('thai', rating: 2);

    $plan = cravingPlan(['craving' => 'thai', 'quick_nights' => ['2026-09-07']]);
    cravingFill($plan);

    $dinners = cravingDinners($plan);

    expect($dinners['2026-09-08'])->toBe($a->id)
        ->and($dinners['2026-09-09'])->toBe($b->id);
});

it('skips a skipped dinner when placing craving dinners', function () {
    $a = cravingRecipe('thai', rating: 3);
    $b = cravingRecipe('thai', rating: 2);

    $plan = cravingPlan(['craving' => 'thai', 'skipped' => [['date' => '2026-09-07', 'slot' => 'dinner']]]);
    cravingFill($plan);

    $dinners = cravingDinners($plan);

    expect($dinners)->not->toHaveKey('2026-09-07')
        ->and($dinners['2026-09-08'])->toBe($a->id)
        ->and($dinners['2026-09-09'])->toBe($b->id);
});

it('gives a quick night a craving dinner only within the quick-night limit', function () {
    $fast = cravingRecipe('thai', minutes: 10, rating: 2);
    cravingRecipe('thai', minutes: 60, rating: 3);

    // Only Monday's dinner is open, and it is a quick night.
    $skipped = collect(['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'])
        ->map(fn ($date) => ['date' => $date, 'slot' => 'dinner'])->all();

    $plan = cravingPlan(['craving' => 'thai', 'quick_nights' => ['2026-09-07'], 'skipped' => $skipped]);
    $result = cravingFill($plan);

    expect(cravingDinners($plan))->toBe(['2026-09-07' => $fast->id])
        ->and($result->unfilled)->toBe(['Only 1 free dinner for your thai craving']);
});

it('reports a shortfall when the library has too few of the craved cuisine', function () {
    $only = cravingRecipe('thai');

    $plan = cravingPlan(['craving' => 'thai']);
    $result = cravingFill($plan);

    expect(collect(cravingDinners($plan))->filter(fn ($id) => $id === $only->id))->toHaveCount(1)
        ->and($result->unfilled)->toBe(['Found only 1 of 2 thai dinners for your craving']);
});

it('reports a craving the library cannot serve at all', function () {
    $result = cravingFill(cravingPlan(['craving' => 'thai']));

    expect($result->unfilled)->toBe(['Found only 0 of 2 thai dinners for your craving']);
});

it('still leaves out avoided-ingredient and missing-tool recipes', function () {
    app(HouseholdPreferences::class)->avoid('cilantro');

    $herby = cravingRecipe('thai');
    $herby->ingredients()->attach(Ingredient::factory()->create(['name' => 'fresh cilantro'])->id, ['qty' => 1, 'unit' => null, 'note' => null]);

    KitchenToolKind::where('name', 'skillet')->update(['owned' => false]);
    $needsSkillet = cravingRecipe('thai');

    $plan = cravingPlan(['craving' => 'thai']);
    $result = cravingFill($plan);

    expect(cravingDinners($plan))->not->toContain($herby->id)->not->toContain($needsSkillet->id)
        ->and($result->unfilled)->toBe(['Found only 0 of 2 thai dinners for your craving']);
});

it('counts craving dinners already planned toward the two', function () {
    $a = cravingRecipe('thai', rating: 3);
    $b = cravingRecipe('thai', rating: 2);

    $plan = cravingPlan(['craving' => 'thai']);
    $plan->plannedMeals()->create(['recipe_id' => $a->id, 'date' => '2026-09-10', 'slot' => 'dinner']);
    $result = cravingFill($plan);

    $dinners = cravingDinners($plan);

    expect($dinners['2026-09-07'])->toBe($b->id)
        ->and(collect($dinners)->filter(fn ($id) => in_array($id, [$a->id, $b->id], true)))->toHaveCount(2)
        ->and($result->unfilled)->toBe([]);
});

it('fills as before when no craving is saved', function () {
    cravingRecipe('thai');

    expect(cravingFill(cravingPlan(['quick_nights' => []]))->unfilled)->toBe([]);
});

it('blames the free dinners, not the library, when the week has too few dinners left', function () {
    cravingRecipe('thai', rating: 3);
    cravingRecipe('thai', rating: 2);
    cravingRecipe('thai', rating: 1);

    $skipped = collect(['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'])
        ->map(fn ($date) => ['date' => $date, 'slot' => 'dinner'])->all();

    $result = cravingFill(cravingPlan(['craving' => 'thai', 'skipped' => $skipped]));

    expect($result->unfilled)->toBe(['Only 1 free dinner for your thai craving']);
});

it('says no dinner is free when every dinner is already taken', function () {
    cravingRecipe('thai', rating: 3);
    cravingRecipe('thai', rating: 2);

    $skipped = collect(['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'])
        ->map(fn ($date) => ['date' => $date, 'slot' => 'dinner'])->all();

    $result = cravingFill(cravingPlan(['craving' => 'thai', 'skipped' => $skipped]));

    expect($result->unfilled)->toBe(['No free dinner left for your thai craving']);
});

it('reports both causes when the library and the free dinners are both short', function () {
    cravingRecipe('thai', rating: 3);

    $skipped = collect(['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'])
        ->map(fn ($date) => ['date' => $date, 'slot' => 'dinner'])->all();

    $result = cravingFill(cravingPlan(['craving' => 'thai', 'skipped' => $skipped]));

    expect($result->unfilled)->toBe([
        'Found only 1 of 2 thai dinners for your craving',
        'Only 1 free dinner for your thai craving',
    ]);
});
