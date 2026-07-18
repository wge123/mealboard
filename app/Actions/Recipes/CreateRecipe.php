<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;

class CreateRecipe
{
    public function __construct(
        private SyncRecipeIngredients $syncIngredients,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{name: string, qty?: mixed, unit?: ?string, note?: ?string}>  $ingredientRows
     */
    public function handle(array $attributes, array $ingredientRows): Recipe
    {
        $recipe = Recipe::create($attributes);

        $this->syncIngredients->handle($recipe, $ingredientRows);

        // Refresh so schema defaults (e.g. status = pending) hydrate the model.
        return $recipe->refresh()->load('ingredients');
    }
}
