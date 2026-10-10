<?php

use App\Livewire\KitchenTools;
use App\Models\KitchenToolOtherName;
use App\Models\User;
use App\Support\KitchenToolInventory;
use Livewire\Livewire;

it('lists a household other name under its kind with a remove control', function () {
    $inventory = new KitchenToolInventory;
    $otherName = $inventory->addOtherName($inventory->resolve('skillet'), 'big pan');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSee('big pan')
        ->assertSeeHtml("data-test=\"remove-other-name-{$otherName->id}\"");
});

it('does not show catalog other names', function () {
    $catalog = KitchenToolOtherName::query()->where('name', 'frying pan')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertDontSee('frying pan')
        ->assertDontSeeHtml("data-test=\"remove-other-name-{$catalog->id}\"");
});

it('removes a household other name from the section', function () {
    $inventory = new KitchenToolInventory;
    $otherName = $inventory->addOtherName($inventory->resolve('skillet'), 'big pan');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('removeOtherName', $otherName->id)
        ->assertHasNoErrors()
        ->assertDontSee('big pan');

    expect($inventory->resolve('big pan'))->toBeNull();
});

it('shows an error and keeps a catalog other name when removal is attempted', function () {
    $catalog = KitchenToolOtherName::query()->where('name', 'frying pan')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('removeOtherName', $catalog->id)
        ->assertHasErrors('removeOtherName');

    expect((new KitchenToolInventory)->resolve('frying pan'))->not->toBeNull();
});
