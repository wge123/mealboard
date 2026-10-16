<?php

use App\Enums\MealSlot;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Livewire\Insights;
use App\Models\MealLog;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;

function logMeal(Recipe $recipe, string $date, MealSlot $slot, bool $ateIt, ?int $rating): void
{
    MealLog::factory()->create([
        'planned_meal_id' => PlannedMeal::factory()->create([
            'recipe_id' => $recipe->id,
            'date' => $date,
            'slot' => $slot,
        ])->id,
        'ate_it' => $ateIt,
        'rating' => $rating,
    ]);
}

function squash(string $html): string
{
    return preg_replace('/\s+/', ' ', trim(strip_tags($html)));
}

/** The text of one table card, found by its heading. */
function insightsCard(string $html, string $title): string
{
    preg_match('/<h2[^>]*>\s*'.preg_quote($title, '/').'\s*<\/h2>.*?<\/section>/s', $html, $match);

    return squash($match[0] ?? '');
}

/** The text of one headline stat, found by its label. */
function insightsStat(string $html, string $label): string
{
    preg_match('/<dt[^>]*>\s*'.preg_quote($label, '/').'\s*<\/dt>.*?<\/div>/s', $html, $match);

    return squash($match[0] ?? '');
}

function seedInsightsLog(): void
{
    $curry = Recipe::factory()->create(['title' => 'Golden Curry', 'cuisine' => 'Thai']);
    $salad = Recipe::factory()->create(['title' => 'Sad Salad', 'cuisine' => 'thai']);
    $toast = Recipe::factory()->create(['title' => 'Toast', 'cuisine' => null]);

    // Week of Mon 20 Jul: Monday dinners, both eaten.
    logMeal($curry, '2026-07-20', MealSlot::Dinner, true, 5);
    logMeal($curry, '2026-07-20', MealSlot::Dinner, true, 4);

    // Same week, Tuesday breakfasts: one eaten, one skipped.
    logMeal($salad, '2026-07-21', MealSlot::Breakfast, true, 2);
    logMeal($salad, '2026-07-21', MealSlot::Breakfast, false, null);

    // Week of Mon 27 Jul: toast skipped twice, never rated.
    logMeal($toast, '2026-07-28', MealSlot::Breakfast, false, null);
    logMeal($toast, '2026-07-29', MealSlot::Breakfast, false, null);

    // Discovered verdicts: 1 approved vs 2 rejected -> 33% kept.
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Approved]);
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Rejected]);
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Rejected]);

    // A manual rejection and a pending discovery must not count.
    Recipe::factory()->create(['source' => RecipeSource::Manual, 'status' => RecipeStatus::Rejected]);
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Pending]);
}

function insightsHtml(): string
{
    return preg_replace('/<!--.*?-->/s', '', test()->actingAs(User::factory()->create())->get('/insights')->assertOk()->getContent());
}

it('renders for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/insights')
        ->assertOk()
        ->assertSeeLivewire(Insights::class);
});

it('redirects guests to login', function () {
    $this->get('/insights')->assertRedirect('/login');
});

it('says in the subtitle what the numbers cover', function () {
    seedInsightsLog();

    $this->actingAs(User::factory()->create())->get('/insights')
        ->assertSeeText('From 6 logged meals in 2 planned weeks between 20 Jul and 2 Aug.');
});

it('pairs each headline label with the figure from the seeded log', function () {
    seedInsightsLog();

    $html = insightsHtml();

    // 3 of 6 eaten; ratings 5, 4, 2 average 3.7; 1 of 3 verdicts kept.
    expect(insightsStat($html, 'Meals logged'))->toBe('Meals logged 6 2 weeks')
        ->and(insightsStat($html, 'Eaten'))->toBe('Eaten 50% 3 ate · 3 skipped')
        ->and(insightsStat($html, 'Average rating'))->toBe('Average rating 3.7 / 5 3 rated meals')
        ->and(insightsStat($html, 'Discoveries kept'))->toBe('Discoveries kept 33% 1 kept · 2 passed');
});

it('lists meals and weekdays with ate, skipped and rate in a fixed order', function () {
    seedInsightsLog();

    $html = insightsHtml();

    // Breakfast: 1 of 4 eaten. Dinner: 2 of 2.
    expect(insightsCard($html, 'By meal'))->toMatch('/Breakfast 1 3 25%.*Dinner 2 0 100%/')
        // Monday 2 of 2, Tuesday 1 of 3, Wednesday 0 of 1.
        ->and(insightsCard($html, 'By weekday'))->toMatch('/Monday 2 0 100%.*Tuesday 1 2 33%.*Wednesday 0 1 0%/');
});

it('ranks top recipes by average rating with the number of rated meals', function () {
    seedInsightsLog();

    $card = insightsCard(insightsHtml(), 'Top recipes');

    expect($card)->toMatch('/1 Golden Curry 4\.5 2×.*2 Sad Salad 2\.0 1×/')
        ->and($card)->not->toContain('Toast');
});

it('ranks the most skipped recipes with how often each was planned', function () {
    seedInsightsLog();

    $card = insightsCard(insightsHtml(), 'Most skipped');

    expect($card)->toMatch('/Toast 2 2.*Sad Salad 1 2/')->not->toContain('Golden Curry');
});

it('groups cuisines ignoring case, with meals, eaten rate and average rating', function () {
    seedInsightsLog();

    $card = insightsCard(insightsHtml(), 'Cuisines');

    // Thai (Golden Curry + Sad Salad): 4 meals, 3 eaten, ratings 5, 4, 2. Toast has no cuisine: 0 of 2 eaten.
    expect($card)->toContain('Thai 4 75% 3.7')->toContain('No cuisine 2 0% —')
        ->and($card)->toMatch('/Thai.*No cuisine/')
        ->and($card)->not->toContain('thai ');
});

it('shows kit empty states for lists with nothing in them', function () {
    logMeal(Recipe::factory()->create(['title' => 'Golden Curry']), '2026-07-20', MealSlot::Dinner, true, null);

    $this->actingAs(User::factory()->create())->get('/insights')
        ->assertOk()
        ->assertSeeText('No rated meals yet.')
        ->assertSeeText('Nothing skipped yet.');
});

it('shows one empty state and no tables when nothing is logged', function () {
    $this->actingAs(User::factory()->create())->get('/insights')
        ->assertOk()
        ->assertSeeText('No meals logged yet')
        ->assertSeeText('Nothing logged yet. Log meals from the plan and this page fills in.')
        ->assertDontSeeText('By weekday')
        ->assertDontSeeText('Cuisines');
});

it('shows dashes for the figures that need data when nothing is logged', function () {
    $html = insightsHtml();

    expect(insightsStat($html, 'Meals logged'))->toBe('Meals logged 0')
        ->and(insightsStat($html, 'Eaten'))->toBe('Eaten — 0 ate · 0 skipped')
        ->and(insightsStat($html, 'Average rating'))->toBe('Average rating — 0 rated meals')
        ->and(insightsStat($html, 'Discoveries kept'))->toBe('Discoveries kept — 0 kept · 0 passed');
});
