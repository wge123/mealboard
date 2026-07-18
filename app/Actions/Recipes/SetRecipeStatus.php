<?php

namespace App\Actions\Recipes;

use App\Enums\RecipeStatus;
use App\Models\Recipe;
use App\Models\User;

class SetRecipeStatus
{
    public function handle(Recipe $recipe, RecipeStatus $status, User $actor): Recipe
    {
        $recipe->status = $status;

        if ($status === RecipeStatus::Approved) {
            $recipe->approved_at = now();
            $recipe->approved_by = $actor->id;
        }

        $recipe->save();

        return $recipe;
    }
}
