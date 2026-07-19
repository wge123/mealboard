<?php

use App\Enums\MealSlot;
use App\Livewire\MealLogControls;
use App\Livewire\PlanBuilder;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonInterface;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** A planned meal on a locked plan, dated $date (defaults to yesterday). */
function lockedMeal(?string $date = null, string $slot = 'dinner'): PlannedMeal
{
    $plan = MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(2)->toDateString(),
    ]);

    return lockedMealOn($plan, $date, $slot);
}

function lockedMealOn(MealPlan $plan, ?string $date = null, string $slot = 'dinner'): PlannedMeal
{
    return $plan->plannedMeals()->create([
        'recipe_id' => Recipe::factory()->approved()->create()->id,
        'date' => $date ?? today()->subDay()->toDateString(),
        'slot' => $slot,
    ]);
}

function logControls(PlannedMeal $meal, ?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->create())
        ->test(MealLogControls::class, ['plannedMeal' => $meal]);
}

it('upserts a single log per user instead of duplicating rows', function () {
    $meal = lockedMeal();
    $user = User::factory()->create();

    logControls($meal, $user)
        ->call('setAte', true)
        ->call('setRating', 4)
        ->set('notes', 'Great with extra lemon');

    // A fresh mount (new page load) updates the same row.
    logControls($meal, $user)->call('setAte', false);

    $log = MealLog::sole();

    expect($log->user_id)->toBe($user->id)
        ->and($log->ate_it)->toBeFalse()
        ->and($log->rating)->toBe(4)
        ->and($log->notes)->toBe('Great with extra lemon');
});

it('mounts with the current user\'s existing log values', function () {
    $meal = lockedMeal();
    $user = User::factory()->create();

    MealLog::factory()->create([
        'planned_meal_id' => $meal->id,
        'user_id' => $user->id,
        'ate_it' => true,
        'rating' => 3,
        'notes' => 'ok',
    ]);

    logControls($meal, $user)
        ->assertSet('ateIt', true)
        ->assertSet('rating', 3)
        ->assertSet('notes', 'ok');
});

it('clears the nullable rating when the same star is tapped again', function () {
    $meal = lockedMeal();
    $user = User::factory()->create();

    logControls($meal, $user)
        ->call('setAte', true)
        ->call('setRating', 5)
        ->call('setRating', 5);

    expect(MealLog::sole()->rating)->toBeNull();
});

it('accepts logs for today\'s slots (past is date-only, today included)', function () {
    $meal = lockedMeal(today()->toDateString());

    logControls($meal)->call('setAte', true);

    expect(MealLog::sole()->ate_it)->toBeTrue();
});

it('rejects logs for future slots server-side', function () {
    $meal = lockedMeal(today()->addDay()->toDateString());

    logControls($meal)->call('setAte', true)->assertStatus(403);

    expect(MealLog::count())->toBe(0);
});

it('rejects logs on a draft plan — logging opens at lock', function () {
    $plan = MealPlan::factory()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(2)->toDateString(),
    ]);
    $meal = lockedMealOn($plan);

    logControls($meal)->call('setAte', true)->assertStatus(403);

    expect(MealLog::count())->toBe(0);
});

it('accepts logs on a completed plan', function () {
    $plan = MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(2)->toDateString(),
        'status' => 'completed',
    ]);
    $meal = lockedMealOn($plan);

    logControls($meal)->call('setAte', false);

    expect(MealLog::sole()->ate_it)->toBeFalse();
});

it('rejects rating and notes before "Ate it?" is answered', function () {
    $meal = lockedMeal();

    logControls($meal)->call('setRating', 4)->assertStatus(422);
    logControls($meal)->set('notes', 'sneaky')->assertStatus(422);

    expect(MealLog::count())->toBe(0);
});

it('lets both users log the same meal independently', function () {
    $meal = lockedMeal();

    $willem = User::factory()->create();
    $partner = User::factory()->create();

    logControls($meal, $willem)->call('setAte', true)->call('setRating', 5);
    logControls($meal, $partner)->call('setAte', false);

    expect(MealLog::count())->toBe(2)
        ->and(MealLog::where('user_id', $willem->id)->sole()->rating)->toBe(5)
        ->and(MealLog::where('user_id', $partner->id)->sole()->ate_it)->toBeFalse();
});

it('shows quick-log controls on the builder for past slots of a locked week', function () {
    $plan = MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(2)->toDateString(),
    ]);

    // The slot must sit inside the plan's rendered Mon–Fri grid. Child
    // components only render full HTML on the INITIAL render, so the plan
    // must be the one mount() auto-selects (it's the only plan).
    lockedMealOn($plan, $plan->week_start_date->toDateString());

    Livewire::actingAs(User::factory()->create())
        ->test(PlanBuilder::class)
        ->assertSee('Ate it?');
});

it('hides quick-log controls for future weeks and draft plans', function (MealPlan $plan) {
    Livewire::actingAs(User::factory()->create())
        ->test(PlanBuilder::class)
        ->assertDontSee('Ate it?');
})->with([
    'locked future week' => fn () => tap(MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->addWeeks(2)->toDateString(),
    ]), fn (MealPlan $plan) => $plan->plannedMeals()->create([
        'recipe_id' => Recipe::factory()->approved()->create()->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => MealSlot::Dinner,
    ])),
    'draft past week' => fn () => tap(MealPlan::factory()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(4)->toDateString(),
    ]), fn (MealPlan $plan) => $plan->plannedMeals()->create([
        'recipe_id' => Recipe::factory()->approved()->create()->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => MealSlot::Dinner,
    ])),
]);
