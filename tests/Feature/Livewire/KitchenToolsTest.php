<?php

use App\Livewire\KitchenTools;
use App\Models\KitchenToolKind;
use App\Models\User;
use App\Support\KitchenToolInventory;
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

it('adds a kind the app does not know, owned straight away', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('newKind', 'Tortilla Press')
        ->call('addKind')
        ->assertHasNoErrors()
        ->assertSet('newKind', '')
        ->assertSee('Tortilla Press');

    expect((new KitchenToolInventory)->owns('tortilla press'))->toBeTrue();
});

it('shows a validation error when the new kind already exists', function (string $word) {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('newKind', $word)
        ->call('addKind')
        ->assertHasErrors('newKind');
})->with(['skillet', 'Frying Pan']);

it('shows a delete control on household kinds only', function () {
    $kind = (new KitchenToolInventory)->addKind('tortilla press');
    $catalog = KitchenToolKind::query()->where('name', 'wok')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeHtml("data-test=\"delete-kind-{$kind->id}\"")
        ->assertDontSeeHtml("data-test=\"delete-kind-{$catalog->id}\"");
});

it('deletes a household kind', function () {
    $kind = (new KitchenToolInventory)->addKind('tortilla press');

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('deleteKind', $kind->id)
        ->assertHasNoErrors()
        ->assertDontSee('Tortilla Press');

    expect(KitchenToolKind::query()->where('name', 'tortilla press')->exists())->toBeFalse();
});

it('refuses to delete a catalog kind with a validation error', function () {
    $catalog = KitchenToolKind::query()->where('name', 'wok')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('deleteKind', $catalog->id)
        ->assertHasErrors('delete');

    expect(KitchenToolKind::query()->where('name', 'wok')->exists())->toBeTrue();
});
