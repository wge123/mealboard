<?php

namespace App\Actions\Recipes;

use App\Enums\IngredientCategory;
use App\Models\Ingredient;
use App\Models\Recipe;

class SyncRecipeIngredients
{
    /**
     * Replace the recipe's ingredient list with the given rows, finding or
     * creating ingredients by (lowercased) name.
     *
     * @param  array<int, array{name: string, qty?: mixed, unit?: ?string, note?: ?string}>  $rows
     */
    public function handle(Recipe $recipe, array $rows): void
    {
        $pivot = [];

        foreach ($rows as $row) {
            $ingredient = Ingredient::firstOrCreate(
                ['name' => mb_strtolower(trim($row['name']))],
                ['category' => IngredientCategory::Other, 'is_pantry_staple' => false],
            );

            $pivot[$ingredient->id] = [
                'qty' => ($row['qty'] ?? null) === '' ? null : ($row['qty'] ?? null),
                'unit' => ($row['unit'] ?? null) ?: null,
                'note' => ($row['note'] ?? null) ?: null,
            ];
        }

        $recipe->ingredients()->sync($pivot);
    }
}
