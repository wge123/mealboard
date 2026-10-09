<?php

namespace App\Actions\Recipes;

use App\Exceptions\RecipeShapeRefused;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class UpdateRecipe
{
    public function __construct(
        private SyncRecipeIngredients $syncIngredients,
        private SyncRecipeShape $syncShape,
    ) {}

    /**
     * Replaces the recipe's shape with the one given; a shape with an empty
     * part (no tools, ingredients or steps) is refused before anything is saved.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{name: string, qty?: mixed, unit?: ?string, note?: ?string, prep_note?: ?string}>  $ingredientRows
     * @param  array<int, array{alternatives: array<int, string>, count?: ?int}>  $tools
     * @param  array<int, string>  $steps
     *
     * @throws RecipeShapeRefused
     */
    public function handle(Recipe $recipe, array $attributes, array $ingredientRows, array $tools, array $steps): Recipe
    {
        $this->syncShape->assertComplete($tools, $ingredientRows, $steps);

        return DB::transaction(function () use ($recipe, $attributes, $ingredientRows, $tools, $steps) {
            $recipe->update($attributes);

            $this->syncIngredients->handle($recipe, $ingredientRows);

            $this->syncShape->handle($recipe, $tools, $steps);

            return $recipe->refresh()->load('ingredients');
        });
    }
}
