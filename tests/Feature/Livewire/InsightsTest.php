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

it('renders for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/insights')
        ->assertOk()
        ->assertSeeLivewire(Insights::class);
});

it('redirects guests to login', function () {
    $this->get('/insights')->assertRedirect('/login');
});

it('shows ate vs skipped rates by slot and weekday, top recipes, and the discovery rejection rate', function () {
    $curry = Recipe::factory()->create(['title' => 'Golden Curry']);
    $salad = Recipe::factory()->create(['title' => 'Sad Salad']);

    // Monday dinners: both eaten (dinner 100%, Monday 100%).
    logMeal($curry, '2026-07-20', MealSlot::Dinner, true, 5);
    logMeal($curry, '2026-07-20', MealSlot::Dinner, true, 4);

    // Tuesday breakfasts: one eaten, one skipped (breakfast 50%, Tuesday 50%).
    logMeal($salad, '2026-07-21', MealSlot::Breakfast, true, 2);
    logMeal($salad, '2026-07-21', MealSlot::Breakfast, false, null);

    // Discovered verdicts: 1 approved vs 2 rejected -> 67% rejected.
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Approved]);
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Rejected]);
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Rejected]);

    // Manual rejection and pending discovery must NOT count.
    Recipe::factory()->create(['source' => RecipeSource::Manual, 'status' => RecipeStatus::Rejected]);
    Recipe::factory()->create(['source' => RecipeSource::Discovered, 'status' => RecipeStatus::Pending]);

    $response = $this->actingAs(User::factory()->create())->get('/insights');

    $response->assertOk()
        // By slot: breakfast 1/1, dinner 2/0.
        ->assertSeeInOrder(['Breakfast', '50%', 'Dinner', '100%'])
        // By weekday: Monday 100%, Tuesday 50%.
        ->assertSeeInOrder(['Monday', '100%', 'Tuesday', '50%'])
        // Top recipes: Golden Curry avg 4.5 (2 logs) above Sad Salad avg 2 (1 log).
        ->assertSeeInOrder(['Golden Curry', '4.5', 'Sad Salad', '2'])
        // Discovery rejection rate: 2 / (1 + 2) = 67%.
        ->assertSeeText('2 rejected vs 1 approved')
        ->assertSeeText('67% rejected');
});

it('shows empty states when there is no data', function () {
    $this->actingAs(User::factory()->create())
        ->get('/insights')
        ->assertOk()
        ->assertSeeText('No meal logs yet.')
        ->assertSeeText('No rated meals yet.')
        ->assertSeeText('No discovery verdicts yet.');
});
