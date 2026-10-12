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
        ->call('openDetails', $otherName->kitchen_tool_kind_id)
        ->assertSee('big pan')
        ->assertSeeHtml("data-test=\"remove-other-name-{$otherName->id}\"");
});

it('lists a catalog other name without a remove control', function () {
    $catalog = KitchenToolOtherName::query()->where('name', 'frying pan')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $catalog->kitchen_tool_kind_id)
        ->assertSee('frying pan')
        ->assertDontSeeHtml("data-test=\"remove-other-name-{$catalog->id}\"");
});

it('says there are no other names yet when the kind has none', function () {
    $kindId = (new KitchenToolInventory)->addKind('Tortilla press')->id;

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $kindId)
        ->assertSee('No other names yet. Add one so recipes that call it something else still match.');
});

it('adds an other name from the details sheet', function () {
    $inventory = new KitchenToolInventory;
    $kind = $inventory->resolve('skillet');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $kind->id)
        ->set('newOtherName', 'big pan')
        ->call('addOtherName', $kind->id)
        ->assertHasNoErrors()
        ->assertSet('newOtherName', '')
        ->assertSee('big pan')
        ->assertDontSee('No other names yet');

    expect($inventory->resolve('big pan')?->id)->toBe($kind->id);
});

it('refuses an other name that already resolves and shows why', function () {
    $kind = (new KitchenToolInventory)->resolve('skillet');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $kind->id)
        ->set('newOtherName', 'frying pan')
        ->call('addOtherName', $kind->id)
        ->assertHasErrors('addOtherName')
        ->assertSee('is already known as');
});

it('refuses an empty other name', function () {
    $kind = (new KitchenToolInventory)->resolve('skillet');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $kind->id)
        ->set('newOtherName', '  ')
        ->call('addOtherName', $kind->id)
        ->assertHasErrors('addOtherName')
        ->assertSee('Enter a name for the kitchen tool.');
});

it('shows an owned toggle in the details sheet that flips ownership', function () {
    $kind = (new KitchenToolInventory)->resolve('wok');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $kind->id)
        ->assertSee('I have this')
        ->assertDontSeeHtml('data-test="detail-owned" checked')
        ->call('toggleOwned', $kind->id)
        ->assertSeeHtml('data-test="detail-owned" checked')
        ->call('closeDetails')
        ->assertSeeInOrder(['Pots', 'Wok', 'Air Fryer']);

    expect((new KitchenToolInventory)->owns($kind->fresh()))->toBeTrue();
});

it('removes a household other name from the section', function () {
    $inventory = new KitchenToolInventory;
    $otherName = $inventory->addOtherName($inventory->resolve('skillet'), 'big pan');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('openDetails', $otherName->kitchen_tool_kind_id)
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
