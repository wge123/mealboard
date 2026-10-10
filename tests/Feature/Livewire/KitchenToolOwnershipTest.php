<?php

use App\Livewire\KitchenTools;
use App\Models\KitchenToolKind;
use App\Models\User;
use App\Support\KitchenToolInventory;
use Livewire\Livewire;

function kindId(string $name): int
{
    return KitchenToolKind::where('name', $name)->value('id');
}

it('toggles a kind owned and moves it into the owned group', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeInOrder(['Pots', 'Stovetop', 'Air Fryer', 'Wok'])
        ->call('toggleOwned', kindId('wok'))
        ->assertSeeInOrder(['Pots', 'Wok', 'Air Fryer']);

    expect((new KitchenToolInventory)->owns(KitchenToolKind::where('name', 'wok')->firstOrFail()))->toBeTrue();
});

it('toggles an owned kind back to not owned', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('toggleOwned', kindId('oven'));

    expect((new KitchenToolInventory)->owns(KitchenToolKind::where('name', 'oven')->firstOrFail()))->toBeFalse();
});

it('saves a note on a kind and shows it', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('notes.'.kindId('skillet'), '12-inch, cast iron')
        ->call('saveNote', kindId('skillet'))
        ->assertSee('12-inch, cast iron');

    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBe('12-inch, cast iron');

    $component->set('notes.'.kindId('skillet'), '')->call('saveNote', kindId('skillet'));

    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBeNull();
});

it('prefills the note field from the stored note', function () {
    (new KitchenToolInventory)->setNote(KitchenToolKind::where('name', 'skillet')->firstOrFail(), 'cast iron');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSet('notes.'.kindId('skillet'), 'cast iron');
});
