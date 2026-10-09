<?php

use App\Exceptions\KitchenToolRefused;
use App\Models\Recipe;
use App\Models\RecipeToolAlternative;
use App\Support\KitchenToolInventory;

function recipeWithUnknownWord(string $word): RecipeToolAlternative
{
    $recipe = Recipe::factory()->create();
    $tool = $recipe->recipeTools()->create(['position' => 2, 'count' => 1]);

    return $tool->alternatives()->create(['position' => 1, 'word' => $word, 'kitchen_tool_kind_id' => null]);
}

it('gives a kind to every unresolved alternative with the word after addKind', function () {
    $first = recipeWithUnknownWord('Tortilla Press');
    $second = recipeWithUnknownWord('tortilla press ');
    $other = recipeWithUnknownWord('tagine');

    $kind = (new KitchenToolInventory)->addKind('tortilla press');

    expect($first->fresh()->kitchen_tool_kind_id)->toBe($kind->id)
        ->and($second->fresh()->kitchen_tool_kind_id)->toBe($kind->id)
        ->and($other->fresh()->kitchen_tool_kind_id)->toBeNull();
});

it('gives a kind to every unresolved alternative with the word after addOtherName', function () {
    $alternative = recipeWithUnknownWord('braiser');
    $inventory = new KitchenToolInventory;
    $skillet = $inventory->resolve('skillet');

    $inventory->addOtherName($skillet, 'Braiser');

    expect($alternative->fresh()->kitchen_tool_kind_id)->toBe($skillet->id);
});

it('leaves an alternative that already has a kind alone', function () {
    $recipe = Recipe::factory()->create();
    $alternative = $recipe->recipeTools->first()->alternatives->first();
    $before = $alternative->kitchen_tool_kind_id;

    (new KitchenToolInventory)->addKind('something new');

    expect($alternative->fresh()->kitchen_tool_kind_id)->toBe($before);
});

it('refuses to delete a kind an alternative points at', function () {
    $inventory = new KitchenToolInventory;
    $kind = $inventory->addKind('tortilla press');
    recipeWithUnknownWord('tortilla press')->update(['kitchen_tool_kind_id' => $kind->id]);

    $inventory->deleteKind($kind);
})->throws(KitchenToolRefused::class, 'Tortilla Press');

it('deletes a kind once nothing references it', function () {
    $inventory = new KitchenToolInventory;
    $kind = $inventory->addKind('tortilla press');
    $alternative = recipeWithUnknownWord('tortilla press');
    $alternative->update(['kitchen_tool_kind_id' => $kind->id]);
    $alternative->delete();

    $inventory->deleteKind($kind);

    expect($inventory->resolve('tortilla press'))->toBeNull();
});
