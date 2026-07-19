<?php

use App\Actions\Planning\PublishMealsMarkdown;
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

/**
 * Contents-API fake: GET answers the sha lookup (404 = file absent),
 * PUT accepts the publish.
 */
function fakeVaultContentsApi(?string $existingSha = null): void
{
    Http::fake(function (Request $request) use ($existingSha) {
        if ($request->method() === 'GET') {
            return $existingSha === null
                ? Http::response('Not Found', 404)
                : Http::response(['sha' => $existingSha]);
        }

        return Http::response(['content' => ['sha' => 'created']], 201);
    });
}

it('publishes the week grid and today file with base64 content and no sha on create', function () {
    $this->travelTo(Carbon::parse('2026-07-20 09:00')); // Monday, ISO week 2026-30.

    fakeVaultContentsApi();

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);

    $salmon = Recipe::factory()->create(['title' => 'Salmon Bowls', 'prep_minutes' => 10, 'cook_minutes' => 15]);
    $curry = Recipe::factory()->create(['title' => 'Chickpea Curry', 'prep_minutes' => 20, 'cook_minutes' => 25]);

    PlannedMeal::factory()->create([
        'meal_plan_id' => $plan->id, 'recipe_id' => $salmon->id,
        'date' => '2026-07-20', 'slot' => MealSlot::Dinner,
    ]);
    PlannedMeal::factory()->create([
        'meal_plan_id' => $plan->id, 'recipe_id' => $curry->id,
        'date' => '2026-07-21', 'slot' => MealSlot::Lunch,
    ]);

    app(PublishMealsMarkdown::class)->handle($plan);

    Http::assertSent(function (Request $request) {
        if ($request->method() !== 'PUT' || ! str_contains($request->url(), 'week-2026-30.md')) {
            return false;
        }

        $markdown = base64_decode($request['content']);

        return $request->url() === 'https://api.github.com/repos/wge123/second-brain-vault/contents/meals/week-2026-30.md'
            && $request['message'] === 'mealboard: publish meals/week-2026-30.md'
            && ! array_key_exists('sha', $request->data())
            && str_contains($markdown, '| Day | Breakfast | Lunch | Dinner |')
            && str_contains($markdown, '| Mon 2026-07-20 | — | — | Salmon Bowls (25 min) |')
            && str_contains($markdown, '| Tue 2026-07-21 | — | Chickpea Curry (45 min) | — |');
    });

    Http::assertSent(function (Request $request) {
        if ($request->method() !== 'PUT' || ! str_ends_with($request->url(), 'contents/meals/today.md')) {
            return false;
        }

        $markdown = base64_decode($request['content']);

        return str_contains($markdown, '# Today — Mon 2026-07-20')
            && str_contains($markdown, '- Breakfast: —')
            && str_contains($markdown, '- Dinner: Salmon Bowls (prep 10 min, cook 15 min)')
            && str_contains($markdown, 'Prep ahead for tomorrow:')
            && str_contains($markdown, '- Chickpea Curry (lunch): prep 20 min');
    });
});

it('includes the existing sha when updating a file that is already in the repo', function () {
    fakeVaultContentsApi(existingSha: 'abc123');

    $plan = MealPlan::factory()->locked()->create(); // far-future — week file only

    app(PublishMealsMarkdown::class)->handle($plan);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/contents/meals/week-')
            && $request['sha'] === 'abc123'
            && base64_decode($request['content']) !== false;
    });
});

it('skips today.md when the plan does not cover today', function () {
    fakeVaultContentsApi();

    $plan = MealPlan::factory()->locked()->create(); // far-future week

    app(PublishMealsMarkdown::class)->handle($plan);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'today.md'));
});

it('refuses to publish outside the meals/ folder', function () {
    Http::fake();

    expect(fn () => app(PublishMealsMarkdown::class)->push('wiki/concepts/evil.md', '# nope'))
        ->toThrow(InvalidArgumentException::class, 'Refusing to publish outside meals/');

    Http::assertNothingSent();
});
