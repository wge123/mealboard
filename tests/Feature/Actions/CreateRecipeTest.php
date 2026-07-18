<?php

use App\Actions\Recipes\CreateRecipe;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\Ingredient;
use App\Models\Recipe;

it('creates a recipe with ingredient pivot rows', function () {
    $recipe = app(CreateRecipe::class)->handle([
        'title' => 'Shakshuka',
        'description' => 'Eggs poached in spiced tomato sauce.',
        'source' => RecipeSource::Manual,
        'meal_type' => 'breakfast',
        'prep_minutes' => 10,
        'cook_minutes' => 20,
        'servings' => 2,
        'instructions' => "1. Simmer sauce.\n2. Poach eggs.",
        'cuisine' => 'middle eastern',
        'tags' => ['vegetarian'],
    ], [
        ['name' => 'Eggs', 'qty' => '4', 'unit' => 'count', 'note' => ''],
        ['name' => 'crushed tomatoes', 'qty' => '400', 'unit' => 'g', 'note' => 'canned'],
    ]);

    expect($recipe->exists)->toBeTrue()
        ->and($recipe->source)->toBe(RecipeSource::Manual)
        ->and($recipe->status)->toBe(RecipeStatus::Pending) // schema default
        ->and($recipe->ingredients)->toHaveCount(2);

    $tomatoes = $recipe->ingredients->firstWhere('name', 'crushed tomatoes');
    expect((float) $tomatoes->pivot->qty)->toBe(400.0)
        ->and($tomatoes->pivot->unit)->toBe('g')
        ->and($tomatoes->pivot->note)->toBe('canned');

    expect(Ingredient::where('name', 'eggs')->exists())->toBeTrue();
    expect(Recipe::count())->toBe(1);
});
