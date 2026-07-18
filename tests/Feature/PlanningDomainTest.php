<?php

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Models\DiscoveryRun;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a meal plan via factory with working enum casts', function () {
    $plan = MealPlan::factory()->create(['status' => 'draft']);

    $plan->refresh();

    expect($plan->status)->toBe(MealPlanStatus::Draft)
        ->and($plan->week_start_date->isMonday())->toBeTrue()
        ->and($plan->locked_at)->toBeNull();
});

it('locks a meal plan via the locked factory state', function () {
    $user = User::factory()->create();
    $plan = MealPlan::factory()->locked($user)->create();

    expect($plan->status)->toBe(MealPlanStatus::Locked)
        ->and($plan->locked_at)->not->toBeNull()
        ->and($plan->lockedBy->is($user))->toBeTrue();
});

it('rejects duplicate week_start_date meal plans', function () {
    MealPlan::factory()->create(['week_start_date' => '2026-07-20']);

    expect(fn () => MealPlan::factory()->create(['week_start_date' => '2026-07-20']))
        ->toThrow(QueryException::class);
});

it('creates a planned meal via factory with slot cast', function () {
    $meal = PlannedMeal::factory()->create(['slot' => 'dinner']);

    $meal->refresh();

    expect($meal->slot)->toBe(MealSlot::Dinner)
        ->and($meal->mealPlan)->toBeInstanceOf(MealPlan::class)
        ->and($meal->recipe->exists)->toBeTrue();
});

it('rejects a duplicate slot on the same plan and date', function () {
    $meal = PlannedMeal::factory()->create();

    expect(fn () => PlannedMeal::factory()->create([
        'meal_plan_id' => $meal->meal_plan_id,
        'date' => $meal->date->toDateString(),
        'slot' => $meal->slot,
    ]))->toThrow(QueryException::class);
});

it('creates a meal log via factory', function () {
    $log = MealLog::factory()->create(['ate_it' => true, 'rating' => 4]);

    $log->refresh();

    expect($log->ate_it)->toBeTrue()
        ->and($log->rating)->toBe(4)
        ->and($log->plannedMeal)->toBeInstanceOf(PlannedMeal::class)
        ->and($log->user)->toBeInstanceOf(User::class);
});

it('rejects a second log from the same user for the same planned meal', function () {
    $log = MealLog::factory()->create();

    expect(fn () => MealLog::factory()->create([
        'planned_meal_id' => $log->planned_meal_id,
        'user_id' => $log->user_id,
    ]))->toThrow(QueryException::class);
});

it('creates a discovery run via factory, including a failed state', function () {
    $run = DiscoveryRun::factory()->create();
    $failed = DiscoveryRun::factory()->failed()->create();

    expect($run->ran_at)->not->toBeNull()
        ->and($run->driver)->toBe('claude-cli')
        ->and($run->error)->toBeNull()
        ->and($failed->error)->not->toBeNull()
        ->and($failed->candidates_found)->toBe(0);
});
