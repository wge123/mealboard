<?php

use App\Livewire\KitchenTools;
use App\Models\User;
use Livewire\Livewire;

it('renders the kitchen tools section for a signed-in household', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/kitchen-tools')
        ->assertOk()
        ->assertSeeLivewire(KitchenTools::class)
        ->assertSee("Chef's Knife")
        ->assertSee('Wok');
});

it('redirects guests to login', function () {
    $this->get('/settings/kitchen-tools')->assertRedirect('/login');
});

it('lists owned kinds before the rest, with title-cased names', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeInOrder(["Chef's Knife", 'Pots', 'Stovetop', 'Air Fryer', 'Wok']);
});
