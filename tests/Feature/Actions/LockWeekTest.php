<?php

use App\Actions\Planning\LockWeek;
use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Models\User;

it('locks a draft plan and stamps locked_at and locked_by', function () {
    $user = User::factory()->create();
    $plan = MealPlan::factory()->create();

    app(LockWeek::class)->handle($plan, $user);

    $plan->refresh();

    expect($plan->status)->toBe(MealPlanStatus::Locked)
        ->and($plan->locked_at)->not->toBeNull()
        ->and($plan->locked_by)->toBe($user->id);
});

it('refuses to lock a plan that is not a draft', function (MealPlanStatus $status) {
    $user = User::factory()->create();
    $plan = MealPlan::factory()->create(['status' => $status]);

    expect(fn () => app(LockWeek::class)->handle($plan, $user))
        ->toThrow(LogicException::class, 'Only draft plans can be locked.');

    expect($plan->refresh()->status)->toBe($status);
})->with([
    'locked' => MealPlanStatus::Locked,
    'completed' => MealPlanStatus::Completed,
]);
