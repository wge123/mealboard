<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;

class UpdateRecipe
{
    public function __construct(
        private SyncRecipeIngredients $syncIngredients,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{name: string, qty?: mixed, unit?: ?string, note?: ?string}>  $ingredientRows
     */
    public function handle(Recipe $recipe, array $attributes, array $ingredientRows): Recipe
    {
        $recipe->update($attributes);

        $this->syncIngredients->handle($recipe, $ingredientRows);

        return $recipe->refresh()->load('ingredients');
    }
}
