<?php

use App\Enums\KitchenToolOrigin;
use App\Livewire\KitchenTools;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
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

    expect((new KitchenToolInventory)->owns(KitchenToolKind::where('name', 'tortilla press')->firstOrFail()))->toBeTrue();
});

it('shows a validation error when the new kind already exists', function (string $word) {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('newKind', $word)
        ->call('addKind')
        ->assertHasErrors('addKind');
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
        ->assertHasErrors('deleteKind');

    expect(KitchenToolKind::query()->where('name', 'wok')->exists())->toBeTrue();
});

it('shows the refusal as a validation error when a recipe still uses the kind', function () {
    $kind = (new KitchenToolInventory)->addKind('tortilla press');
    $recipe = Recipe::factory()->create();
    $recipe->recipeTools->first()->alternatives()->create(['position' => 2, 'word' => 'tortilla press', 'kitchen_tool_kind_id' => $kind->id]);

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('deleteKind', $kind->id)
        ->assertHasErrors('deleteKind')
        ->assertSee('kitchen tools, so it cannot be deleted');

    expect(KitchenToolKind::query()->where('name', 'tortilla press')->exists())->toBeTrue();
});

it('saves a note on a kind added in the same session', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('newKind', 'Tortilla.Press')
        ->call('addKind');

    $id = KitchenToolKind::where('name', 'tortilla.press')->value('id');

    $component->set('notes.'.$id, 'cast iron')->call('saveNote', $id);

    expect(KitchenToolKind::find($id)->note)->toBe('cast iron');
});

it('drops the note entry of a deleted kind', function () {
    $kind = KitchenToolKind::create(['name' => 'zester', 'origin' => KitchenToolOrigin::Household, 'owned' => true, 'note' => 'fine']);

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSet('notes.'.$kind->id, 'fine')
        ->call('deleteKind', $kind->id)
        ->assertSet('notes', fn ($n) => ! array_key_exists($kind->id, $n));
});

it('renders the settings section menu on the kitchen tools page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/kitchen-tools')
        ->assertOk()
        ->assertSee('aria-label="Settings sections"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSeeInOrder(['Kitchen tools', 'Channels']);
});

it('keeps item variants out of the catalog kinds and resolves them to their kind', function (string $variant) {
    $inventory = new KitchenToolInventory;

    expect($inventory->kinds())->not->toContain($variant)
        ->and($inventory->resolve($variant)?->name)->toBe('skillet');
})->with(['cast iron skillet', 'nonstick pan']);
