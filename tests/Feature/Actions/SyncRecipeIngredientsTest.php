<?php

use App\Actions\Recipes\SyncRecipeIngredients;
use App\Models\Ingredient;
use App\Models\Recipe;

it('stores the bare ingredient name and puts the amount from the name on the pivot', function () {
    $recipe = Recipe::factory()->create();

    app(SyncRecipeIngredients::class)->handle($recipe, [
        ['name' => '200g Tomato'],
        ['name' => 'pinch of salt'],
        ['name' => '2cm piece ginger', 'prep_note' => 'grated'],
    ]);

    $names = $recipe->ingredients()->orderBy('name')->get();

    expect($names->pluck('name')->all())->toBe(['ginger', 'salt', 'tomato'])
        ->and(Ingredient::query()->where('name', 'like', '%200g%')->exists())->toBeFalse();

    $tomato = $names->firstWhere('name', 'tomato');
    expect((float) $tomato->pivot->qty)->toBe(200.0)
        ->and($tomato->pivot->unit)->toBe('g');

    expect($names->firstWhere('name', 'salt')->pivot->note)->toBe('pinch of')
        ->and($names->firstWhere('name', 'ginger')->pivot->note)->toBe('grated, 2cm piece');
});

it('lets the amount the row gives win over the one in the name', function () {
    $recipe = Recipe::factory()->create();

    app(SyncRecipeIngredients::class)->handle($recipe, [
        ['name' => '200g tomato', 'qty' => 3, 'unit' => 'count'],
    ]);

    $tomato = $recipe->ingredients()->first();

    expect($tomato->name)->toBe('tomato')
        ->and((float) $tomato->pivot->qty)->toBe(3.0)
        ->and($tomato->pivot->unit)->toBe('count');
});

it('matches a cleaned name to the ingredient a pantry staple already uses', function () {
    $staple = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $recipe = Recipe::factory()->create();

    app(SyncRecipeIngredients::class)->handle($recipe, [['name' => '1/2 tsp salt']]);

    expect($recipe->ingredients()->first()->is($staple))->toBeTrue()
        ->and(Ingredient::query()->where('name', 'like', '%salt%')->count())->toBe(1);
});

it('keeps a name that is nothing but an amount word', function () {
    $recipe = Recipe::factory()->create();

    app(SyncRecipeIngredients::class)->handle($recipe, [['name' => 'pinch']]);

    expect($recipe->ingredients()->first()->name)->toBe('pinch');
});
