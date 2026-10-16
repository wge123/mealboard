<?php

use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Livewire\RecipeLibrary;
use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\MealLog;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
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

it('narrows results with text search on title, description and tags', function () {
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

    Recipe::factory()->approved()->create(['title' => 'Batch Chili', 'tags' => ['freezer-friendly']]);

    $component->set('search', 'freezer')
        ->assertSee('Batch Chili')
        ->assertDontSee('Beef Tacos');
});

it('filters by meal type', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast]);
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->call('toggle', 'mealTypes', 'breakfast')
        ->assertSee('Overnight Oats')
        ->assertDontSee('Steak Frites');
});

it('filters by cuisine', function () {
    Recipe::factory()->approved()->create(['title' => 'Margherita Pizza', 'cuisine' => 'italian']);
    Recipe::factory()->approved()->create(['title' => 'Pad Krapow', 'cuisine' => 'thai']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->call('toggle', 'cuisines', 'thai')
        ->assertSee('Pad Krapow')
        ->assertDontSee('Margherita Pizza');
});

it('filters by tag', function () {
    Recipe::factory()->approved()->create(['title' => 'Speedy Stir Fry', 'tags' => ['quick', 'healthy']]);
    Recipe::factory()->approved()->create(['title' => 'Sunday Roast', 'tags' => ['comfort']]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->call('toggle', 'tags', 'quick')
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

    $component->set('minRating', 4)
        ->assertSee('Crowd Pleaser Curry')
        ->assertDontSee('Forgettable Flatbread')
        ->assertDontSee('Unrated Udon');
});

it('badges a recipe whose kitchen tool is not owned', function () {
    KitchenToolKind::where('name', 'skillet')->update(['owned' => false]);
    Recipe::factory()->approved()->create(['title' => 'Stir fry']);
    Recipe::factory()->unshaped()->approved()->create(['title' => 'Plain toast']);

    $this->actingAs(User::factory()->create());

    $component = Livewire::test(RecipeLibrary::class)
        ->assertSee('needs: skillet')
        ->assertSee('Plain toast');

    expect(substr_count($component->html(), 'data-missing-badge'))->toBe(1);
});

it('shows no badge once the tool is owned', function () {
    KitchenToolKind::where('name', 'skillet')->update(['owned' => true]);
    Recipe::factory()->approved()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(RecipeLibrary::class)->assertDontSee('needs:');
});

function library(): Testable
{
    return Livewire::actingAs(User::factory()->create())->test(RecipeLibrary::class);
}

it('shows recipes on shelves by meal until a search or filter is used, then as a wall', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast]);
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner]);

    $component = library()
        ->assertSeeHtml('data-test="shelf-breakfast"')
        ->assertSeeHtml('data-test="shelf-dinner"')
        ->assertDontSeeHtml('data-test="wall"')
        ->assertSeeInOrder(['Breakfast', 'Overnight Oats', 'Dinner', 'Steak Frites']);

    $component->set('search', 'oats')
        ->assertSeeHtml('data-test="wall"')
        ->assertDontSeeHtml('data-test="shelf-breakfast"');

    $component->set('search', '')->call('toggle', 'mealTypes', 'dinner')
        ->assertSeeHtml('data-test="wall"')
        ->assertSee('Steak Frites')
        ->assertDontSee('Overnight Oats');
});

it('regroups the shelves by time, cuisine and rating', function () {
    Recipe::factory()->approved()->create(['title' => 'Slow Roast', 'prep_minutes' => 20, 'cook_minutes' => 90, 'cuisine' => 'british']);
    Recipe::factory()->approved()->create(['title' => 'Fast Oats', 'prep_minutes' => 5, 'cook_minutes' => 5, 'cuisine' => 'scottish']);
    Recipe::factory()->approved()->create(['title' => 'Mystery Stew', 'prep_minutes' => 0, 'cook_minutes' => 0, 'cuisine' => 'british']);
    rateRecipe(Recipe::where('title', 'Fast Oats')->sole(), 5);

    $component = library();

    $component->set('groupBy', 'time')
        ->assertSeeInOrder(['Quick', 'Fast Oats', 'Takes a while', 'Slow Roast', 'Time unknown', 'Mystery Stew']);

    $component->set('groupBy', 'cuisine')
        ->assertSeeInOrder(['British', 'Mystery Stew', 'Slow Roast', 'Scottish', 'Fast Oats']);

    $component->set('groupBy', 'rating')
        ->assertSeeInOrder(['Loved', 'Fast Oats', 'Not rated yet', 'Mystery Stew']);
});

it('combines chips within a facet as either-or', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast]);
    Recipe::factory()->approved()->create(['title' => 'Club Sandwich', 'meal_type' => MealType::Lunch]);
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner]);

    library()
        ->call('toggle', 'mealTypes', 'breakfast')
        ->call('toggle', 'mealTypes', 'lunch')
        ->assertSee('Overnight Oats')
        ->assertSee('Club Sandwich')
        ->assertDontSee('Steak Frites')
        // A second tap turns a chip off again.
        ->call('toggle', 'mealTypes', 'lunch')
        ->assertDontSee('Club Sandwich');
});

it('filters by most total minutes, leaving out recipes with no time', function () {
    Recipe::factory()->approved()->create(['title' => 'Fast Oats', 'prep_minutes' => 5, 'cook_minutes' => 10]);
    Recipe::factory()->approved()->create(['title' => 'Slow Roast', 'prep_minutes' => 20, 'cook_minutes' => 90]);
    Recipe::factory()->approved()->create(['title' => 'Mystery Stew', 'prep_minutes' => 0, 'cook_minutes' => 0]);

    library()->set('maxMinutes', 20)
        ->assertSee('Fast Oats')
        ->assertDontSee('Slow Roast')
        ->assertDontSee('Mystery Stew');
});

it('counts each meal chip among the recipes the other filters leave', function () {
    Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner, 'cuisine' => 'thai']);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner, 'cuisine' => 'thai']);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner, 'cuisine' => 'italian']);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Lunch, 'cuisine' => 'thai']);

    library()
        ->assertSeeHtml('aria-label="Dinner, 3 recipes"')
        ->call('toggle', 'cuisines', 'thai')
        ->assertSeeHtml('aria-label="Dinner, 2 recipes"')
        ->assertSeeHtml('aria-label="Lunch, 1 recipe"')
        // Its own facet does not narrow the count: picking dinner keeps lunch at 1.
        ->call('toggle', 'mealTypes', 'dinner')
        ->assertSeeHtml('aria-label="Lunch, 1 recipe"');
});

it('clears every filter at once and goes back to the shelves', function () {
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner, 'cuisine' => 'french', 'tags' => ['quick']]);

    library()
        ->set('search', 'steak')
        ->call('toggle', 'mealTypes', 'dinner')
        ->call('toggle', 'cuisines', 'french')
        ->call('toggle', 'tags', 'quick')
        ->set('maxMinutes', 60)
        ->set('minRating', 4)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('mealTypes', [])
        ->assertSet('cuisines', [])
        ->assertSet('tags', [])
        ->assertSet('maxMinutes', null)
        ->assertSet('minRating', null)
        ->assertSeeHtml('data-test="shelf-dinner"');
});

it('refuses a chip for a facet that does not exist', function () {
    library()->call('toggle', 'groupBy', 'time')->assertStatus(422);
});

it('links Request a recipe from the page header to the request page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/recipes')
        ->assertOk()
        ->assertSee('Request a recipe')
        ->assertSee('href="'.route('recipes.request').'"', false);
});

it('offers one filter menu per facet and says how many values are on', function () {
    Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner, 'cuisine' => 'thai', 'tags' => ['quick']]);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Lunch, 'cuisine' => 'italian', 'tags' => ['quick']]);

    library()
        ->assertSeeHtml('role="toolbar"')
        ->assertDontSeeHtml('aria-label="Meal, ')
        ->call('toggle', 'mealTypes', 'dinner')
        ->call('toggle', 'mealTypes', 'lunch')
        ->call('toggle', 'cuisines', 'thai')
        ->assertSeeHtml('aria-label="Meal, 2 on"')
        ->assertSeeHtml('aria-label="Cuisine, 1 on"')
        ->set('maxMinutes', 40)
        ->assertSeeHtml('aria-label="Time, ≤ 40 min"');
});

it('turns the 4 and up chip on and off', function () {
    library()
        ->assertSeeHtml('4 and up')
        ->set('minRating', 4)
        ->assertSet('minRating', 4)
        ->set('minRating', null)
        ->assertSet('minRating', null);
});

it('keeps the old chip rows out of the page', function () {
    Recipe::factory()->approved()->create(['cuisine' => 'thai', 'tags' => ['quick']]);
    Recipe::factory()->approved()->create(['cuisine' => 'thai', 'tags' => ['quick']]);

    library()
        ->assertDontSeeHtml('aria-label="Meal and time"')
        ->assertDontSeeHtml('aria-label="Cuisine and tags"');
});

// --- Table view (desktop) ---------------------------------------------------

it('opens on the shelves unless the address asks for the table', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats']);

    library()->assertSet('view', 'shelves')->assertDontSeeHtml('data-test="library-table"');

    Livewire::withQueryParams(['view' => 'table'])
        ->actingAs(User::factory()->create())
        ->test(RecipeLibrary::class)
        ->assertSet('view', 'table')
        ->assertSeeHtml('data-test="library-table"')
        ->assertSee('Overnight Oats');
});

it('writes the view and the sort to the address', function () {
    Recipe::factory()->approved()->create();

    $component = library()->set('view', 'table');
    expect($component->effects['url'] ?? [])->toHaveKey('view');

    $component->call('sortBy', 'time');
    expect($component->effects['url'])->toMatchArray(['sort' => 'time', 'dir' => 'asc']);
});

it('keeps the phone on the shelves while the table is chosen', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast]);

    library()->set('view', 'table')
        ->assertSeeHtml('data-test="library-table"')
        ->assertSeeHtml('data-test="shelf-breakfast"');
});

it('lists the filtered recipes in the table, grouped by the current group-by', function () {
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast, 'cuisine' => 'scottish']);
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner, 'cuisine' => 'french']);
    Recipe::factory()->approved()->create(['title' => 'Moules Frites', 'meal_type' => MealType::Dinner, 'cuisine' => 'french']);

    $component = library()->set('view', 'table');
    $table = fn () => (string) str($component->html())->after('data-test="library-table"')->before('</table>');

    expect($table())->toContain('Breakfast', 'Overnight Oats', 'Dinner', 'Steak Frites', 'Moules Frites');

    $component->set('groupBy', 'cuisine');
    expect($table())->toContain('French', 'Scottish');

    $component->set('cuisines', ['french']);
    expect($table())->toContain('Steak Frites')->not->toContain('Overnight Oats')->not->toContain('Scottish');
});

it('sorts every table column both ways', function (string $column, array $expectedAscending) {
    $quick = Recipe::factory()->approved()->create(['title' => 'Quick Soup', 'meal_type' => MealType::Dinner, 'cuisine' => 'british', 'prep_minutes' => 5, 'cook_minutes' => 5]);
    $slow = Recipe::factory()->approved()->create(['title' => 'Slow Roast', 'meal_type' => MealType::Dinner, 'cuisine' => 'french', 'prep_minutes' => 20, 'cook_minutes' => 90]);
    Recipe::factory()->unshaped()->approved()->create(['title' => 'Plain Rice', 'meal_type' => MealType::Dinner, 'cuisine' => 'asian', 'prep_minutes' => 5, 'cook_minutes' => 20]);
    rateRecipe($slow, 5);
    rateRecipe($quick, 2);
    $slow->ingredients()->attach(Ingredient::factory()->count(3)->create()->pluck('id'), ['qty' => 1, 'unit' => 'count', 'note' => null]);
    $quick->ingredients()->attach(Ingredient::factory()->create()->id, ['qty' => 1, 'unit' => 'count', 'note' => null]);
    KitchenToolKind::where('name', 'skillet')->update(['owned' => false]);

    $component = library()->set('view', 'table');
    $order = fn () => collect(['Quick Soup', 'Slow Roast', 'Plain Rice'])
        ->sortBy(fn (string $title) => strpos((string) str($component->html())->after('data-test="library-table"'), $title))
        ->values()->all();

    $component->call('sortBy', $column);
    expect($order())->toBe($expectedAscending);

    $component->call('sortBy', $column);
    expect($order())->toBe(array_reverse($expectedAscending));
})->with([
    'recipe' => ['title', ['Plain Rice', 'Quick Soup', 'Slow Roast']],
    'cuisine' => ['cuisine', ['Plain Rice', 'Quick Soup', 'Slow Roast']],
    'time' => ['time', ['Quick Soup', 'Plain Rice', 'Slow Roast']],
    'rating' => ['rating', ['Plain Rice', 'Quick Soup', 'Slow Roast']],
    'ingredients' => ['ingredients', ['Plain Rice', 'Quick Soup', 'Slow Roast']],
    'kitchen tools' => ['tools', ['Plain Rice', 'Quick Soup', 'Slow Roast']],
]);

it('sorts the meal column in the order of the day', function () {
    Recipe::factory()->approved()->create(['title' => 'Steak Frites', 'meal_type' => MealType::Dinner]);
    Recipe::factory()->approved()->create(['title' => 'Overnight Oats', 'meal_type' => MealType::Breakfast]);
    Recipe::factory()->approved()->create(['title' => 'Club Sandwich', 'meal_type' => MealType::Lunch]);

    library()->set('view', 'table')->set('groupBy', 'rating')
        ->call('sortBy', 'meal')
        ->assertSeeInOrder(['Overnight Oats', 'Club Sandwich', 'Steak Frites'])
        ->call('sortBy', 'meal')
        ->assertSeeInOrder(['Steak Frites', 'Club Sandwich', 'Overnight Oats']);
});

it('marks the sorted column and its direction for assistive tech', function () {
    Recipe::factory()->approved()->create();

    library()->set('view', 'table')
        ->assertSeeHtml('aria-sort="ascending"')
        ->call('sortBy', 'time')
        ->assertSeeHtml('aria-sort="ascending"')
        ->call('sortBy', 'time')
        ->assertSeeHtml('aria-sort="descending"');
});

it('refuses to sort by a column the table does not have', function () {
    library()->call('sortBy', 'password')->assertStatus(422);
});
