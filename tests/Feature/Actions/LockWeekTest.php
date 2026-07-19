<?php

use App\Actions\Planning\LockWeek;
use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // No PAT by default: the post-lock publish fails fast and is swallowed,
    // so lock behavior stays observable without HTTP. Publish-specific tests
    // below set a PAT and fake the contents API.
    config()->set('mealboard.github_pat', null);
});

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

it('publishes the locked week to the vault meals folder', function () {
    config()->set('mealboard.github_pat', 'test-pat');
    config()->set('mealboard.brain_repo', 'wge123/second-brain-vault');

    Http::fake(function (Request $request) {
        return $request->method() === 'GET'
            ? Http::response('Not Found', 404)
            : Http::response(['content' => ['sha' => 'created']], 201);
    });

    $lockWeek = app(LockWeek::class);
    $lockWeek->handle(MealPlan::factory()->create(), User::factory()->create());

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/contents/meals/week-'));

    expect($lockWeek->publishWarning)->toBeNull();
});

it('keeps the lock and surfaces a warning when the vault publish fails', function () {
    $plan = MealPlan::factory()->create();

    $lockWeek = app(LockWeek::class);
    $lockWeek->handle($plan, User::factory()->create()); // no PAT -> publish throws

    expect($plan->refresh()->status)->toBe(MealPlanStatus::Locked)
        ->and($lockWeek->publishWarning)->toContain('Week locked, but vault publish failed')
        ->and($lockWeek->publishWarning)->toContain('GITHUB_PAT not set');
});
