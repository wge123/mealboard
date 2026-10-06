<?php

namespace App\Actions\Planning;

use App\Models\Ingredient;
use App\Models\WalmartMatchProposal;
use App\Models\WalmartRejectedItem;
use App\Support\WalmartCartLink;
use DomainException;

class ProposeProductMatch
{
    public function __construct(private WalmartCartLink $cartLink) {}

    /**
     * Park a matcher's guess for review instead of saving it as a match.
     * Refuses a URL with no usItemId (it could never cart), an ingredient
     * that already has a confirmed match, and a product a human rejected
     * for this ingredient before. One proposal per ingredient, upserted.
     *
     * @throws DomainException
     */
    public function handle(Ingredient $ingredient, string $productUrl, string $productName, ?string $confidence = null): WalmartMatchProposal
    {
        $itemId = $this->cartLink->itemId($productUrl);

        if ($itemId === null) {
            throw new DomainException("No Walmart item id in {$productUrl}: propose a walmart.com/ip/<slug>/<id> URL.");
        }

        if ($confidence !== null && ! in_array($confidence, WalmartMatchProposal::CONFIDENCES, true)) {
            throw new DomainException('Confidence must be one of: '.implode(', ', WalmartMatchProposal::CONFIDENCES).'.');
        }

        if ($ingredient->walmartMatch()->exists()) {
            throw new DomainException("{$ingredient->name} already has a confirmed match.");
        }

        $rejected = WalmartRejectedItem::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('item_id', $itemId)
            ->exists();

        if ($rejected) {
            throw new DomainException("Item {$itemId} was rejected for {$ingredient->name}: propose a different product.");
        }

        return WalmartMatchProposal::updateOrCreate(
            ['ingredient_id' => $ingredient->id],
            [
                'product_url' => $productUrl,
                'product_name' => $productName,
                'confidence' => $confidence,
            ],
        );
    }
}
