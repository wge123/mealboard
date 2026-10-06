<?php

namespace App\Actions\Planning;

use App\Models\Ingredient;
use App\Models\WalmartMatch;

class SaveProductMatch
{
    /**
     * Remember (or re-confirm) the Walmart product for an ingredient: one
     * match per ingredient, upserted, confirmation stamp refreshed. A new
     * product starts with no availability: the old product's flag says
     * nothing about it.
     */
    public function handle(Ingredient $ingredient, string $productUrl, string $productName): WalmartMatch
    {
        $match = WalmartMatch::firstOrNew(['ingredient_id' => $ingredient->id]);

        if ($match->product_url !== $productUrl) {
            $match->availability = null;
            $match->availability_seen_at = null;
        }

        $match->fill([
            'product_url' => $productUrl,
            'product_name' => $productName,
            'last_confirmed_at' => now(),
        ])->save();

        return $match;
    }
}
