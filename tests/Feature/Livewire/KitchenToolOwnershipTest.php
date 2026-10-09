<?php

use App\Livewire\KitchenTools;
use App\Models\KitchenToolKind;
use App\Models\User;
use App\Support\KitchenToolInventory;
use Livewire\Livewire;

it('toggles a kind owned and moves it into the owned group', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeInOrder(['Pots', 'Stovetop', 'Air Fryer', 'Wok'])
        ->call('toggleOwned', 'wok')
        ->assertSeeInOrder(['Pots', 'Wok', 'Air Fryer']);

    expect((new KitchenToolInventory)->owns('wok'))->toBeTrue();
});

it('toggles an owned kind back to not owned', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('toggleOwned', 'oven');

    expect((new KitchenToolInventory)->owns('oven'))->toBeFalse();
});

it('saves a note on a kind and shows it', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('notes.skillet', '12-inch, cast iron')
        ->call('saveNote', 'skillet')
        ->assertSee('12-inch, cast iron');

    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBe('12-inch, cast iron');

    $component->set('notes.skillet', '')->call('saveNote', 'skillet');

    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBeNull();
});

it('prefills the note field from the stored note', function () {
    (new KitchenToolInventory)->setNote('skillet', 'cast iron');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSet('notes.skillet', 'cast iron');
});
