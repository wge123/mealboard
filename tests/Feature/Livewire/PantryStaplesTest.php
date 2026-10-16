<?php

use App\Livewire\PantryStaples;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

it('renders the pantry staples section with staples first for a signed-in household', function () {
    Ingredient::factory()->create(['name' => 'avocado', 'category' => 'bakery']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'soy sauce', 'category' => 'bakery']);

    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertSeeLivewire(PantryStaples::class)
        ->assertSeeInOrder(['soy sauce', 'avocado']);
});

it('searches the ingredients', function () {
    Ingredient::factory()->create(['name' => 'soy sauce']);
    Ingredient::factory()->create(['name' => 'avocado']);

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('search', 'soy')
        ->assertSee('soy sauce')
        ->assertDontSee('avocado');
});

it('toggles an ingredient between staple and not', function () {
    $salt = Ingredient::factory()->create(['name' => 'salt']);

    $component = Livewire::actingAs(User::factory()->create())->test(PantryStaples::class);

    $component->call('toggle', $salt->id);
    expect($salt->refresh()->is_pantry_staple)->toBeTrue();

    $component->call('toggle', $salt->id);
    expect($salt->refresh()->is_pantry_staple)->toBeFalse();
});

it('adds a staple by name when no recipe uses it yet', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('newStaple', 'Soy Sauce')
        ->call('addStaple')
        ->assertHasNoErrors()
        ->assertSet('newStaple', '')
        ->assertSee('soy sauce');

    expect(Ingredient::where('name', 'soy sauce')->sole()->is_pantry_staple)->toBeTrue();
});

it('shows the refusal for a blank staple name', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('newStaple', '   ')
        ->call('addStaple')
        ->assertHasErrors('newStaple');

    expect(Ingredient::count())->toBe(0);
});

it('lists every staple even when the other ingredients are capped', function () {
    Ingredient::factory()->count(70)->create();
    foreach (range(1, 65) as $i) {
        Ingredient::factory()->pantryStaple()->create(['name' => sprintf('zz staple %02d', $i)]);
    }

    $component = Livewire::actingAs(User::factory()->create())->test(PantryStaples::class);

    foreach (range(1, 65) as $i) {
        $component->assertSee(sprintf('zz staple %02d', $i));
    }
});

it('filters the tiles to staples or to everything else', function () {
    Ingredient::factory()->create(['name' => 'avocado']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'soy sauce']);

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('filter', 'staples')
        ->assertSee('soy sauce')
        ->assertDontSee('avocado')
        ->set('filter', 'others')
        ->assertSee('avocado')
        ->assertDontSee('soy sauce');
});

it('marks the picked ingredients as staples or not in one go and tells the settings menu', function () {
    $salt = Ingredient::factory()->create(['name' => 'salt']);
    $rice = Ingredient::factory()->create(['name' => 'rice']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->call('markMany', [$salt->id, $rice->id], true)
        ->assertDispatched('settings-changed');

    expect($salt->refresh()->is_pantry_staple)->toBeTrue()
        ->and($rice->refresh()->is_pantry_staple)->toBeTrue();

    $component->call('markMany', [$rice->id], false);

    expect($rice->refresh()->is_pantry_staple)->toBeFalse();
});

it('tells the settings menu when a tap or a new staple changes the staples', function () {
    $salt = Ingredient::factory()->create(['name' => 'salt']);

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->call('toggle', $salt->id)
        ->assertDispatched('settings-changed');

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('newStaple', 'cumin')
        ->call('addStaple')
        ->assertDispatched('settings-changed');
});

it('refuses a filter it does not know', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('filter', 'bogus');
})->throws(ValueError::class);

it('lists the other ingredients most used by recipes first', function () {
    $rice = Ingredient::factory()->create(['name' => 'rice', 'category' => 'bakery']);
    $avocado = Ingredient::factory()->create(['name' => 'avocado', 'category' => 'bakery']);

    foreach (Recipe::factory()->count(3)->create() as $recipe) {
        $recipe->ingredients()->attach($rice->id, ['qty' => 1, 'unit' => 'cup']);
    }
    Recipe::factory()->create()->ingredients()->attach($avocado->id, ['qty' => 1, 'unit' => 'count']);

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->assertSeeInOrder(['rice', 'avocado']);
});

it('says how many of the other ingredients it shows when they are capped', function () {
    Ingredient::factory()->count(70)->create();

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->assertSee('Showing the 60 most used. Search to find the rest.');
});

it('gives every tile the sort keys the browser re-sorts a tapped tile by, matching the server order', function () {
    $rice = Ingredient::factory()->create(['name' => 'rice', 'category' => 'bakery']);
    $basil = Ingredient::factory()->create(['name' => 'basil', 'category' => 'bakery']);
    Ingredient::factory()->create(['name' => 'avocado', 'category' => 'bakery']);
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt', 'category' => 'bakery']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'cumin', 'category' => 'bakery']);

    foreach (Recipe::factory()->count(3)->create() as $recipe) {
        $recipe->ingredients()->attach([$rice->id => ['qty' => 1, 'unit' => 'cup'], $salt->id => ['qty' => 1, 'unit' => 'tsp']]);
    }
    Recipe::factory()->create()->ingredients()->attach($basil->id, ['qty' => 1, 'unit' => 'count']);

    [$server, $browser] = tileOrders(Livewire::actingAs(User::factory()->create())->test(PantryStaples::class)->html());

    // The recipe factory adds ingredients of its own, so only the head is fixed.
    expect(array_slice($server, 0, 3))->toBe(['Staple: cumin', 'Staple: salt', 'Staple: rice'])
        ->and(array_search('Staple: basil', $server))->toBeLessThan(array_search('Staple: avocado', $server))
        ->and($browser)->toBe($server);
});

it('gives select mode a way out and action labels that say what they do', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->assertSee('aria-label="Stop selecting"', false)
        ->assertSee('Remove staples')
        ->assertSee('Make staples');
});

it('groups the ingredients under their aisle and says how many of each aisle are on hand', function () {
    Ingredient::factory()->create(['name' => 'basil', 'category' => 'produce']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'garlic', 'category' => 'produce']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'rice', 'category' => 'pantry']);

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->assertSeeTextInOrder(['Pantry', '1 of 1 on hand', 'rice', 'Produce', '1 of 2 on hand', 'garlic', 'basil']);
});

it('shows how many recipes use each ingredient on its line', function () {
    $rice = Ingredient::factory()->create(['name' => 'rice']);
    foreach (Recipe::factory()->count(3)->create() as $recipe) {
        $recipe->ingredients()->attach($rice->id, ['qty' => 1, 'unit' => 'cup']);
    }

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->assertSeeText('used in 3 recipes');
});

it('counts an aisle from the ingredients it shows when a filter narrows the list', function () {
    Ingredient::factory()->create(['name' => 'basil', 'category' => 'produce']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'garlic', 'category' => 'produce']);

    Livewire::actingAs(User::factory()->create())
        ->test(PantryStaples::class)
        ->set('filter', 'staples')
        ->assertSeeText('1 of 1 on hand');
});

it('lists each ingredient as a checkbox that is ticked when it is a staple', function () {
    Ingredient::factory()->create(['name' => 'basil', 'category' => 'bakery']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'garlic', 'category' => 'bakery']);

    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8">'.Livewire::actingAs(User::factory()->create())->test(PantryStaples::class)->html());
    $boxes = [];
    foreach ((new DOMXPath($dom))->query('//input[@type="checkbox"]') as $box) {
        $boxes[$box->getAttribute('aria-label')] = $box->hasAttribute('checked');
    }

    expect($boxes)->toBe(['Staple: garlic' => true, 'Staple: basil' => false]);
});
