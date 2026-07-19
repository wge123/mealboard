<?php

use App\Enums\MealSlot;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('mealboard.github_pat', 'test-pat');
    config()->set('mealboard.brain_repo', 'wge123/second-brain-vault');
});

it('skips quietly when no locked week covers today', function () {
    $this->travelTo(Carbon::parse('2026-07-21 00:10')); // Tuesday, no plans at all.

    Http::fake();

    $this->artisan('meals:publish-today')
        ->expectsOutputToContain('No locked week covers today — nothing published.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('publishes today.md for the current locked week', function () {
    $this->travelTo(Carbon::parse('2026-07-21 00:10')); // Tuesday of week 2026-07-20.

    Http::fake(function (Request $request) {
        return $request->method() === 'GET'
            ? Http::response('Not Found', 404)
            : Http::response(['content' => ['sha' => 'created']], 201);
    });

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);

    PlannedMeal::factory()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => Recipe::factory()->create(['title' => 'Shakshuka', 'prep_minutes' => 5, 'cook_minutes' => 20])->id,
        'date' => '2026-07-21',
        'slot' => MealSlot::Breakfast,
    ]);

    $this->artisan('meals:publish-today')
        ->expectsOutputToContain('meals/today.md published.')
        ->assertSuccessful();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PUT'
            && str_ends_with($request->url(), '/contents/meals/today.md')
            && str_contains(base64_decode($request['content']), '- Breakfast: Shakshuka (prep 5 min, cook 20 min)');
    });
});
