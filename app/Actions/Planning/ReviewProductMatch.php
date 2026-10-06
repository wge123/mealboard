<?php

namespace App\Actions\Planning;

use App\Models\WalmartMatchProposal;
use App\Models\WalmartRejectedItem;
use App\Support\WalmartCartLink;
use Illuminate\Support\Facades\DB;

class ReviewProductMatch
{
    public function __construct(
        private SaveProductMatch $saveProductMatch,
        private WalmartCartLink $cartLink,
    ) {}

    /**
     * Settle one proposal. Three outcomes:
     *  - a pasted "found it" URL is saved as the confirmed match, and the
     *    proposed product (when it differs) joins the rejected list;
     *  - a "replace" tick alone rejects the proposed product, so the next
     *    matcher run skips it, and leaves the ingredient unmatched;
     *  - neither confirms the proposal as the match.
     *
     * The tick is a preference, not a stock claim: out-of-stock this week is
     * handled by Walmart's pickup substitution, not by rejecting.
     */
    public function handle(WalmartMatchProposal $proposal, bool $replace, ?string $foundUrl = null): void
    {
        DB::transaction(function () use ($proposal, $replace, $foundUrl) {
            $ingredient = $proposal->ingredient;
            $proposedId = $this->cartLink->itemId($proposal->product_url);

            if ($foundUrl !== null) {
                if ($proposedId !== null && $proposedId !== $this->cartLink->itemId($foundUrl)) {
                    $this->reject($proposal, $proposedId);
                }

                $this->saveProductMatch->handle($ingredient, $foundUrl, $ingredient->name);

                return;
            }

            if ($replace) {
                if ($proposedId !== null) {
                    $this->reject($proposal, $proposedId);
                }

                $proposal->delete();

                return;
            }

            $this->saveProductMatch->handle($ingredient, $proposal->product_url, $proposal->product_name);
        });
    }

    private function reject(WalmartMatchProposal $proposal, string $itemId): void
    {
        WalmartRejectedItem::firstOrCreate([
            'ingredient_id' => $proposal->ingredient_id,
            'item_id' => $itemId,
        ]);
    }
}
