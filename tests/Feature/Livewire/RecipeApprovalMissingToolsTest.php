<?php

use App\Livewire\RecipeApproval;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Models\User;
use App\Support\KitchenToolInventory;
use App\Support\MissingKitchenTools;
use Livewire\Livewire;

function pendingRecipeNeeding(string $word, ?KitchenToolKind $kind = null, array $attributes = []): Recipe
{
    $recipe = Recipe::factory()->create($attributes);
    $recipe->recipeTools()->each(fn ($tool) => $tool->delete());
    $recipe->recipeTools()->create(['position' => 1, 'count' => 1])
        ->alternatives()->create(['position' => 1, 'word' => $word, 'kitchen_tool_kind_id' => $kind?->id]);

    return $recipe;
}

it('shows the missing tools on the approval card', function () {
    pendingRecipeNeeding('tortilla press');

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('needs: tortilla press')
        ->assertSee('Add as a new tool');
});

it('shows no flag when the household owns the tool', function () {
    KitchenToolKind::where('name', 'skillet')->update(['owned' => true]);
    Recipe::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertDontSee('needs:');
});

it('clears the flag here and on another recipe when added as a new tool', function () {
    $older = pendingRecipeNeeding('tortilla press', null, ['created_at' => now()->subDay()]);
    $other = pendingRecipeNeeding('Tortilla Press');

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('addAsNewTool', 'tortilla press')
        ->assertDontSee('needs:');

    expect(app(MissingKitchenTools::class)->flaggedIds([$older, $other]))->toBeEmpty();
});

it('clears the flag everywhere when named as an owned kind through an other name', function () {
    $older = pendingRecipeNeeding('braiser', null, ['created_at' => now()->subDay()]);
    $other = pendingRecipeNeeding('braiser');
    $skillet = app(KitchenToolInventory::class)->resolve('skillet');
    app(KitchenToolInventory::class)->setOwned($skillet, true);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('nameAsMyTool', 'braiser', $skillet->id)
        ->assertDontSee('needs:');

    expect(app(MissingKitchenTools::class)->flaggedIds([$older, $other]))->toBeEmpty()
        ->and(app(KitchenToolInventory::class)->resolve('braiser')->id)->toBe($skillet->id);
});

it('offers to mark a known kind owned and clears the flag', function () {
    $wok = app(KitchenToolInventory::class)->resolve('wok');
    app(KitchenToolInventory::class)->setOwned($wok, false);
    pendingRecipeNeeding('wok', $wok);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('needs: wok')
        ->assertSee('Mark Wok as owned')
        ->call('markToolOwned', $wok->id)
        ->assertDontSee('needs:');

    expect($wok->fresh()->owned)->toBeTrue();
});

it('shows an error and changes nothing when the word is already known', function () {
    pendingRecipeNeeding('tortilla press');

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('addAsNewTool', 'skillet')
        ->assertHasErrors('tool');
});
