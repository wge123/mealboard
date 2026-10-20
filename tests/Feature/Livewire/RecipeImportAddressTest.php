<?php

use App\Discovery\RecipeSiteDriver;
use App\Livewire\RecipeCreate;
use App\Models\Recipe;
use App\Models\RecipeSite;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** A one-recipe page, with the given extras in its head and its Recipe node. */
function addressPage(string $head = '', array $node = []): string
{
    return '<html><head>'.$head.'<script type="application/ld+json">'.json_encode([
        '@type' => 'Recipe', 'name' => 'Plain Rice', 'description' => 'Rice.',
        'recipeIngredient' => ['1 cup rice'], 'recipeInstructions' => ['Cook the rice in a pot.'],
        'prepTime' => 'PT5M', 'cookTime' => 'PT15M', 'recipeYield' => '4',
    ] + $node).'</script></head><body></body></html>';
}

it('opens the library recipe for a pasted address once tracking parameters are stripped, without fetching', function () {
    Http::fake();
    $existing = Recipe::factory()->create(['source_url' => 'https://cook.example/rice/']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://cook.example/rice/?utm_source=newsletter&utm_medium=email&fbclid=abc#comments')
        ->call('importLink')
        ->assertRedirect("/recipes/{$existing->id}");

    Http::assertNothingSent();
});

it('fetches the pasted address without its tracking parameters and keeps the others', function () {
    Http::fake(['*' => Http::response(addressPage())]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://cook.example/rice/?utm_campaign=x&p=42&gclid=zzz')
        ->call('importLink')
        ->assertSet('title', 'Plain Rice')
        ->assertSet('sourceUrl', 'https://cook.example/rice/?p=42');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cook.example/rice/?p=42');
});

it('opens the library recipe at the page canonical address instead of importing a second copy', function (string $head, array $node) {
    $existing = Recipe::factory()->create(['source_url' => 'https://cook.example/rice/']);
    Http::fake(['*' => Http::response(addressPage($head, $node))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://cook.example/short-link-7')
        ->call('importLink')
        ->assertRedirect("/recipes/{$existing->id}")
        ->assertSet('title', '');
})->with([
    'a link rel=canonical' => ['<link rel="canonical" href="https://cook.example/rice/">', []],
    'the Recipe url' => ['', ['url' => 'https://cook.example/rice/']],
    'mainEntityOfPage as a string' => ['', ['mainEntityOfPage' => 'https://cook.example/rice/']],
    'mainEntityOfPage as a WebPage with an id' => ['', ['mainEntityOfPage' => ['@type' => 'WebPage', '@id' => 'https://cook.example/rice/']]],
]);

it('stores the page canonical address as the imported recipe source', function () {
    Http::fake(['*' => Http::response(addressPage('<link rel="canonical" href="https://cook.example/rice/">'))]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://cook.example/short-link-7?utm_source=x')
        ->call('importLink')
        ->assertSet('sourceUrl', 'https://cook.example/rice/');

    $component->call('save')->assertHasNoErrors();

    expect(Recipe::firstWhere('title', 'Plain Rice')->source_url)->toBe('https://cook.example/rice/');
});

it('ignores a canonical address that is not a web address', function () {
    Http::fake(['*' => Http::response(addressPage('<link rel="canonical" href="javascript:alert(1)">'))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://cook.example/rice/')
        ->call('importLink')
        ->assertSet('sourceUrl', 'https://cook.example/rice/');
});

it('fetches a discovered post without its tracking parameters and keeps the others', function () {
    config()->set('mealboard.drivers', [RecipeSiteDriver::class]);
    RecipeSite::query()->delete();
    RecipeSite::factory()->create(['feed_url' => 'https://cook.example/feed/', 'name' => 'Cook']);
    Http::fake([
        'https://cook.example/feed/' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><title>C</title><item><link>https://cook.example/?p=5&amp;utm_source=rss#more</link></item></channel></rss>'),
        '*' => Http::response('<html><body>An article.</body></html>'),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cook.example/?p=5');
});
