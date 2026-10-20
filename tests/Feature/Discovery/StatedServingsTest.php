<?php

use App\Discovery\CandidateValidator;
use App\Discovery\RecipePageReader;
use App\Discovery\RecipeSiteDriver;
use App\Livewire\RecipeCreate;
use App\Models\Recipe;
use App\Models\RecipeSite;
use App\Models\SeenPost;
use App\Models\User;
use App\Support\HouseholdPreferences;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const SERVINGS_URL = 'https://servings.example/a/';

/** A one-recipe page, with a yield only when given. */
function servingsPage(?string $yield): string
{
    return '<script type="application/ld+json">'.json_encode(array_filter([
        '@type' => 'Recipe', 'name' => 'Plain Rice', 'description' => 'Rice.',
        'recipeIngredient' => ['1 cup rice'], 'recipeInstructions' => ['Cook the rice in a pot.'],
        'prepTime' => 'PT5M', 'cookTime' => 'PT15M', 'recipeYield' => $yield,
    ], fn ($v) => $v !== null)).'</script>';
}

it('reads null, not 0, for servings when the page states no yield', function () {
    $candidate = app(RecipePageReader::class)->read(servingsPage(null), SERVINGS_URL)->candidate;

    expect($candidate['servings'])->toBeNull();
});

it('reads the servings a page states', function () {
    $candidate = app(RecipePageReader::class)->read(servingsPage('4 servings'), SERVINGS_URL)->candidate;

    expect($candidate['servings'])->toBe(4);
});

it('keeps the household size on the form when an imported page states no servings', function () {
    app(HouseholdPreferences::class)->setHouseholdSize(4);
    Http::fake(['*' => Http::response(servingsPage(null))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', SERVINGS_URL)
        ->call('importLink')
        ->assertSet('title', 'Plain Rice')
        ->assertSet('servings', 4);
});

it('refuses a discovered recipe-site post that states no servings, and remembers it was fetched', function () {
    config()->set('mealboard.drivers', [RecipeSiteDriver::class]);
    RecipeSite::query()->delete();
    RecipeSite::factory()->create(['feed_url' => 'https://servings.example/feed/', 'name' => 'Servings']);
    Http::fake([
        'https://servings.example/feed/' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><title>S</title><item><link>'.SERVINGS_URL.'</link></item></channel></rss>'),
        SERVINGS_URL => Http::response(servingsPage(null)),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();

    expect(Recipe::count())->toBe(0)
        ->and(SeenPost::count())->toBe(1);
});

it('refuses servings below 1 on a recipe to be stored', function (mixed $servings) {
    $check = (new CandidateValidator)->check([
        'title' => 'Rice', 'description' => 'Rice.', 'meal_type' => 'dinner',
        'prep_minutes' => 5, 'cook_minutes' => 10, 'servings' => $servings,
        'source_url' => SERVINGS_URL,
        'tools' => [['alternatives' => ['pot'], 'count' => 1]],
        'ingredients' => [['qty' => 1.0, 'unit' => 'cup', 'name' => 'rice', 'prep_note' => null]],
        'steps' => ['Cook the rice.'],
    ]);

    expect($check->passes())->toBeFalse()
        ->and($check->errors)->toContain('servings: must be a whole number of 1 or more');
})->with([0, -2, '0']);
