<?php

use App\Actions\Recipes\UpdateRecipe;
use App\Models\Ingredient;
use App\Models\Recipe;

it('updates attributes and replaces ingredient rows', function () {
    $recipe = Recipe::factory()->create(['title' => 'Before']);
    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'stale bread'])->id,
        ['qty' => 1, 'unit' => 'count', 'note' => null],
    );

    $updated = app(UpdateRecipe::class)->handle($recipe, [
        'title' => 'After',
        'servings' => 6,
    ], [
        ['name' => 'Fresh Basil', 'qty' => '0.5', 'unit' => 'cup', 'note' => 'torn'],
    ]);

    expect($updated->title)->toBe('After')
        ->and($updated->servings)->toBe(6)
        ->and($updated->ingredients)->toHaveCount(1);

    $basil = $updated->ingredients->first();
    expect($basil->name)->toBe('fresh basil') // find-or-create lowercases
        ->and((float) $basil->pivot->qty)->toBe(0.5)
        ->and($basil->pivot->unit)->toBe('cup')
        ->and($basil->pivot->note)->toBe('torn');
});

it('reuses an existing ingredient regardless of case', function () {
    $existing = Ingredient::factory()->create(['name' => 'garlic']);
    $recipe = Recipe::factory()->create();

    app(UpdateRecipe::class)->handle($recipe, [], [
        ['name' => 'Garlic', 'qty' => '2', 'unit' => 'count', 'note' => 'minced'],
    ]);

    expect(Ingredient::where('name', 'garlic')->count())->toBe(1)
        ->and($recipe->fresh()->ingredients->first()->id)->toBe($existing->id);
});

it('stores empty qty, unit, and note as null', function () {
    $recipe = Recipe::factory()->create();

    app(UpdateRecipe::class)->handle($recipe, [], [
        ['name' => 'eggs', 'qty' => '', 'unit' => '', 'note' => ''],
    ]);

    $pivot = $recipe->fresh()->ingredients->first()->pivot;

    expect($pivot->qty)->toBeNull()
        ->and($pivot->unit)->toBeNull()
        ->and($pivot->note)->toBeNull();
});
