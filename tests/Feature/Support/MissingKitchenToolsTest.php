<?php

use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Support\KitchenToolInventory;
use App\Support\MissingKitchenTools;
use Illuminate\Support\Facades\DB;

/** A shaped recipe whose tools are the given entries (each a list of words). */
function shapedRecipeNeeding(array $entries): Recipe
{
    $recipe = Recipe::factory()->approved()->shaped()->create();
    $recipe->recipeTools()->delete();

    foreach ($entries as $position => $words) {
        $tool = $recipe->recipeTools()->create(['position' => $position + 1, 'count' => 1]);

        foreach ($words as $i => $word) {
            $tool->alternatives()->create([
                'position' => $i + 1,
                'word' => $word,
                'kitchen_tool_kind_id' => app(KitchenToolInventory::class)->resolve($word)?->id,
            ]);
        }
    }

    return $recipe;
}

function setOwnership(string $name, bool $owned): void
{
    KitchenToolKind::where('name', $name)->update(['owned' => $owned]);
}

it('treats an entry as covered when any one alternative is owned', function () {
    setOwnership('wok', false);
    setOwnership('skillet', true);
    $recipe = shapedRecipeNeeding([['wok', 'skillet']]);

    expect(app(MissingKitchenTools::class)->for($recipe))->toBeEmpty();
});

it('returns the uncovered entries with each alternative word and kind', function () {
    setOwnership('wok', false);
    setOwnership('skillet', false);
    $recipe = shapedRecipeNeeding([['wok', 'skillet']]);

    $missing = app(MissingKitchenTools::class)->for($recipe);

    expect($missing)->toHaveCount(1)
        ->and($missing[0]->label())->toBe('wok or skillet')
        ->and($missing[0]->alternatives)->toHaveCount(2)
        ->and($missing[0]->alternatives[0]['word'])->toBe('wok')
        ->and($missing[0]->alternatives[0]['kind']->name)->toBe('wok');
});

it('counts an unknown kind as missing and keeps its word', function () {
    setOwnership('skillet', true);
    $recipe = shapedRecipeNeeding([['flux capacitor']]);

    $missing = app(MissingKitchenTools::class)->for($recipe);

    expect($missing)->toHaveCount(1)
        ->and($missing[0]->alternatives[0]['kind'])->toBeNull()
        ->and($missing[0]->label())->toBe('flux capacitor');
});

it('clears the flag as soon as the kind is marked owned', function () {
    setOwnership('wok', false);
    $recipe = shapedRecipeNeeding([['wok']]);
    $query = app(MissingKitchenTools::class);

    expect($query->for($recipe))->toHaveCount(1);

    app(KitchenToolInventory::class)->setOwned(KitchenToolKind::where('name', 'wok')->first(), true);

    expect($query->for($recipe))->toBeEmpty();
});

it('gives a recipe without the shape no missing tools', function () {
    $recipe = Recipe::factory()->approved()->create();

    expect(app(MissingKitchenTools::class)->for($recipe))->toBeEmpty();
});

it('answers many recipes in a constant number of queries', function () {
    setOwnership('wok', false);
    $recipes = collect(range(1, 6))->map(fn () => shapedRecipeNeeding([['wok']]));
    $unshaped = Recipe::factory()->approved()->create();

    DB::enableQueryLog();
    $byRecipe = app(MissingKitchenTools::class)->forMany($recipes->push($unshaped));
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(6)
        ->and($byRecipe[$recipes[0]->id])->toHaveCount(1)
        ->and($byRecipe[$unshaped->id])->toBeEmpty();
});
