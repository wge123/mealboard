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
     * Pass `$tools` and/or `$steps` to replace the recipe shape; leave both
     * null to keep the saved shape. A shape with an empty part is refused
     * before anything is saved.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{name: string, qty?: mixed, unit?: ?string, note?: ?string, prep_note?: ?string}>  $ingredientRows
     * @param  ?array<int, array{alternatives: array<int, string>, count?: ?int}>  $tools
     * @param  ?array<int, string>  $steps
     *
     * @throws RecipeShapeRefused
     */
    public function handle(Recipe $recipe, array $attributes, array $ingredientRows, ?array $tools = null, ?array $steps = null): Recipe
    {
        $shaped = $tools !== null || $steps !== null;

        if ($shaped) {
            $this->syncShape->assertComplete($tools ?? [], $ingredientRows, $steps ?? []);
        }

        return DB::transaction(function () use ($recipe, $attributes, $ingredientRows, $tools, $steps, $shaped) {
            $recipe->update($attributes);

            $this->syncIngredients->handle($recipe, $ingredientRows);

            if ($shaped) {
                $this->syncShape->handle($recipe, $tools ?? [], $steps ?? []);
            }

            return $recipe->refresh()->load('ingredients');
        });
    }
}
