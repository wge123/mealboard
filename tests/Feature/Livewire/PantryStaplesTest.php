<?php

use App\Livewire\PantryStaples;
use App\Models\Ingredient;
use App\Models\User;
use Livewire\Livewire;

it('renders the pantry staples section with staples first for a signed-in household', function () {
    Ingredient::factory()->create(['name' => 'avocado']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'soy sauce']);

    $this->actingAs(User::factory()->create())
        ->get('/settings/pantry-staples')
        ->assertOk()
        ->assertSeeLivewire(PantryStaples::class)
        ->assertSeeInOrder(['soy sauce', 'avocado']);
});

it('redirects guests to login', function () {
    $this->get('/settings/pantry-staples')->assertRedirect('/login');
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
