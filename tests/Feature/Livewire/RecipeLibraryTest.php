<?php

use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Livewire\RecipeLibrary;
use App\Models\MealLog;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

function rateRecipe(Recipe $recipe, int ...$ratings): void
{
    foreach ($ratings as $rating) {
        MealLog::factory()->create([
            'planned_meal_id' => PlannedMeal::factory()->create(['recipe_id' => $recipe->id])->id,
            'ate_it' => true,
            'rating' => $rating,
        ]);
    }
}

it('renders the recipe library for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/recipes')
        ->assertOk()
        ->assertSeeLivewire(RecipeLibrary::class);
});

it('redirects guests to login', function () {
    $this->get('/recipes')->assertRedirect('/login');
});

it('lists only approved recipes', function () {
    Recipe::factory()->approved()->create(['title' => 'Approved Green Curry']);
    Recipe::factory()->create(['title' => 'Pending Ramen', 'status' => RecipeStatus::Pending]);
    Recipe::factory()->create(['title' => 'Rejected Casserole', 'status' => RecipeStatus::Rejected]);
    Recipe::factory()->create(['title' => 'Archived Stew', 'status' => RecipeStatus::Archived]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->assertSee('Approved Green Curry')
        ->assertDontSee('Pending Ramen')
        ->assertDontSee('Rejected Casserole')
        ->assertDontSee('Archived Stew');
});

it('narrows results with text search on title and description', function () {
    Recipe::factory()->approved()->create(['title' => 'Lemon Chicken', 'description' => 'Bright and zesty.']);
    Recipe::factory()->approved()->create(['title' => 'Beef Tacos', 'description' => 'Weeknight favourite with lime crema.']);
    Recipe::factory()->approved()->create(['title' => 'Mushroom Risotto', 'description' => 'Slow stirred.']);

    $component = Livewire::actingAs(User::factory()->create())->test(RecipeLibrary::class);

    $component->set('search', 'lemon')
        ->assertSee('Lemon Chicken')
        ->assertDontSee('Beef Tacos')
        ->assertDontSee('Mushroom Risotto');

    // Description matches too.
    $component->set('search', 'lime crema')
        ->assertSee('Beef Tacos')
        ->assertDontSee('Lemon Chicken');
});

it('filters by meal type', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast]);
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->set('mealType', 'breakfast')
        ->assertSee('Overnight Oats')
        ->assertDontSee('Steak Frites');
});

it('filters by cuisine', function () {
    Recipe::factory()->approved()->create(['title' => 'Margherita Pizza', 'cuisine' => 'italian']);
    Recipe::factory()->approved()->create(['title' => 'Pad Krapow', 'cuisine' => 'thai']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->set('cuisine', 'thai')
        ->assertSee('Pad Krapow')
        ->assertDontSee('Margherita Pizza');
});

it('filters by tag', function () {
    Recipe::factory()->approved()->create(['title' => 'Speedy Stir Fry', 'tags' => ['quick', 'healthy']]);
    Recipe::factory()->approved()->create(['title' => 'Sunday Roast', 'tags' => ['comfort']]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->set('tag', 'quick')
        ->assertSee('Speedy Stir Fry')
        ->assertDontSee('Sunday Roast');
});

it('filters by minimum average rating', function () {
    $loved = Recipe::factory()->approved()->create(['title' => 'Crowd Pleaser Curry']);
    $meh = Recipe::factory()->approved()->create(['title' => 'Forgettable Flatbread']);
    Recipe::factory()->approved()->create(['title' => 'Unrated Udon']);

    rateRecipe($loved, 4, 5); // avg 4.5
    rateRecipe($meh, 2, 2);   // avg 2.0

    $component = Livewire::actingAs(User::factory()->create())->test(RecipeLibrary::class);

    // No rating filter: unrated recipes pass.
    $component
        ->assertSee('Crowd Pleaser Curry')
        ->assertSee('Forgettable Flatbread')
        ->assertSee('Unrated Udon');

    $component->set('minRating', '4')
        ->assertSee('Crowd Pleaser Curry')
        ->assertDontSee('Forgettable Flatbread')
        ->assertDontSee('Unrated Udon');
});
