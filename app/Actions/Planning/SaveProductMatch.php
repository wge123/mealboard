<?php

namespace App\Actions\Planning;

use App\Models\Ingredient;
use App\Models\WalmartMatch;
use App\Models\WalmartMatchProposal;

class SaveProductMatch
{
    /**
     * Remember (or re-confirm) the Walmart product for an ingredient: one
     * match per ingredient, upserted, confirmation stamp refreshed. A
     * confirmed match settles any pending proposal for the ingredient.
     */
    public function handle(Ingredient $ingredient, string $productUrl, string $productName): WalmartMatch
    {
        WalmartMatchProposal::query()->where('ingredient_id', $ingredient->id)->delete();

        return WalmartMatch::updateOrCreate(
            ['ingredient_id' => $ingredient->id],
            [
                'product_url' => $productUrl,
                'product_name' => $productName,
                'last_confirmed_at' => now(),
            ],
        );
    }
}
