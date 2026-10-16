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
        ->get('/settings')
        ->assertOk()
        ->assertSeeLivewire(KitchenTools::class)
        ->assertSee("Chef's Knife")
        ->assertSee('Wok');
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

it('shows a delete control in the details of household kinds only', function () {
    $kind = (new KitchenToolInventory)->addKind('tortilla press');
    $catalog = KitchenToolKind::query()->where('name', 'wok')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertDontSeeHtml("data-test=\"delete-kind-{$kind->id}\"")
        ->call('openDetails', $kind->id)
        ->assertSeeHtml("data-test=\"delete-kind-{$kind->id}\"")
        ->call('openDetails', $catalog->id)
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

it('keeps item variants out of the catalog kinds and resolves them to their kind', function (string $variant) {
    $inventory = new KitchenToolInventory;

    expect($inventory->kinds())->not->toContain($variant)
        ->and($inventory->resolve($variant)?->name)->toBe('skillet');
})->with(['cast iron skillet', 'nonstick pan']);

it('filters the tiles to owned kinds or kinds the household does not have', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('filter', 'not-owned')
        ->assertSee('Wok')
        ->assertDontSee("Chef's Knife")
        ->set('filter', 'owned')
        ->assertSee("Chef's Knife")
        ->assertDontSee('Wok');
});

it('marks the picked kinds owned or not owned in one go and tells the settings menu', function () {
    $wok = KitchenToolKind::where('name', 'wok')->firstOrFail();
    $oven = KitchenToolKind::where('name', 'oven')->firstOrFail();
    $inventory = new KitchenToolInventory;

    $component = Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('markMany', [$wok->id], true)
        ->assertDispatched('settings-changed');

    expect($inventory->owns($wok))->toBeTrue();

    $component->call('markMany', [$wok->id, $oven->id], false);

    expect($inventory->owns($wok))->toBeFalse()
        ->and($inventory->owns($oven))->toBeFalse();
});

it('tells the settings menu when one tap changes ownership', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->call('toggleOwned', KitchenToolKind::where('name', 'wok')->value('id'))
        ->assertDispatched('settings-changed');
});

it('shows a kind\'s note field only in its details, and closes them', function () {
    $wok = KitchenToolKind::where('name', 'wok')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertDontSeeHtml('aria-label="Note for Wok"')
        ->call('openDetails', $wok->id)
        ->assertSeeHtml('aria-label="Note for Wok"')
        ->call('closeDetails')
        ->assertDontSeeHtml('aria-label="Note for Wok"');
});

it('refuses a filter it does not know', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('filter', 'bogus');
})->throws(ValueError::class);

it('names the filter in plain words when nothing matches', function () {
    KitchenToolKind::query()->update(['owned' => true]);

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->set('filter', 'not-owned')
        ->assertSee('You have every kitchen tool the app knows.');
});

it('keeps small words like and lowercase in a kitchen tool name', function () {
    expect((new KitchenToolKind)->forceFill(['name' => 'mortar and pestle'])->display_name)->toBe('Mortar and Pestle');
});

it('gives every tile the sort keys the browser re-sorts a tapped tile by, matching the server order', function () {
    KitchenToolKind::query()->update(['owned' => false]);
    KitchenToolKind::query()->whereIn('name', ['wok', 'oven'])->update(['owned' => true]);

    [$server, $browser] = tileOrders(Livewire::actingAs(User::factory()->create())->test(KitchenTools::class)->html());

    // The Owned heading sorts first, then the owned tools by name.
    expect(array_slice($server, 0, 3))->toBe(['Owned 2', 'Owned: Oven', 'Owned: Wok'])
        ->and($browser)->toBe($server);
});

it('gives select mode a way out and action labels that say what they do', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSee('aria-label="Stop selecting"', false)
        ->assertSee('Mark not owned')
        ->assertSee('Mark owned');
});

/** @param  list<string>  $names  kind names one recipe's single tool entry offers */
function recipeNeedingKinds(array $names): void
{
    $recipe = Recipe::factory()->create();
    $recipe->recipeTools()->delete();
    $tool = $recipe->recipeTools()->create(['position' => 1, 'count' => 1]);

    foreach ($names as $i => $name) {
        $tool->alternatives()->create(['position' => $i + 1, 'word' => $name, 'kitchen_tool_kind_id' => KitchenToolKind::where('name', $name)->value('id')]);
    }
}

it('splits the tools into Owned and Don\'t have groups with their counts', function () {
    KitchenToolKind::query()->update(['owned' => false]);
    KitchenToolKind::query()->whereIn('name', ['wok', 'oven'])->update(['owned' => true]);
    $total = KitchenToolKind::count();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeInOrder(['Owned', '2', 'Oven', 'Wok', "Don't have", (string) ($total - 2), 'Air Fryer'])
        ->assertSeeHtml('data-state="on"')
        ->assertSeeHtml('data-state="off"');
});

it('moves a tool to the other group when it is toggled', function () {
    $wok = KitchenToolKind::where('name', 'wok')->firstOrFail();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeInOrder(["Don't have", 'Wok'])
        ->call('toggleOwned', $wok->id);

    $component->assertSeeInOrder(['Owned', 'Wok', "Don't have"]);
    expect($wok->fresh()->owned)->toBeTrue();
});

it('shows how many recipes call for each tool', function () {
    recipeNeedingKinds(['wok']);
    recipeNeedingKinds(['wok', 'skillet']);

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSee('used in 2 recipes')
        ->assertSee('used in 1 recipe');
});

it('flags a tool the household does not have that recipes call for', function () {
    recipeNeedingKinds(['wok']);
    recipeNeedingKinds(['oven']);

    $html = Livewire::actingAs(User::factory()->create())->test(KitchenTools::class)->html();

    // The wok is not owned and a recipe needs it; the oven is owned, so it is not flagged.
    $text = html_entity_decode($html, ENT_QUOTES);

    expect($text)->toContain("used in 1 recipe, and you don't have it")
        ->and(substr_count($text, "you don't have it"))->toBe(1);
});

it('does not flag a not-owned tool no recipe calls for', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertDontSee("you don't have it");
});

it('gives every tool line a details button that opens the sheet', function () {
    $wok = KitchenToolKind::where('name', 'wok')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(KitchenTools::class)
        ->assertSeeHtml('aria-label="Details for Wok"')
        ->call('openDetails', $wok->id)
        ->assertSeeHtml('aria-label="Wok details"')
        ->assertSeeHtml('aria-label="Note for Wok"');
});
