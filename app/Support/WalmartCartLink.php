<?php

namespace App\Support;

/**
 * Walmart's affiliate add-to-cart link — the one write path that needs no
 * automation at all (docs/tier3-research.md §3). Mealboard emits a URL and the
 * human clicks it in their own browser, so no request of ours is ever scored by
 * PerimeterX.
 *
 * `?items=` takes the usItemId, which is the trailing numeric segment of the
 * canonical `walmart.com/ip/<slug>/<usItemId>` URLs `walmart_matches` stores.
 * Quantity is deliberately left off (`|<qty>` defaults to 1): shopping-list
 * quantities are recipe amounts ("500 g flour"), not pack counts, so passing
 * them through would cart 500 bags of flour.
 */
class WalmartCartLink
{
    private const ADD_TO_CART = 'https://affil.walmart.com/cart/addToCart?items=';

    /**
     * One add-to-cart link for every product URL that yields an item id, or
     * null when none does — a bare `?items=` URL is worse than no link.
     *
     * @param  list<string>  $productUrls  canonical Walmart product URLs
     */
    public function handle(array $productUrls): ?string
    {
        $ids = [];

        foreach ($productUrls as $url) {
            $id = $this->itemId($url);

            // Dedupe: two unit buckets of one ingredient are still one product.
            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids === [] ? null : self::ADD_TO_CART.implode(',', $ids);
    }

    /** The usItemId of a canonical product URL, or null if it carries none. */
    public function itemId(string $url): ?string
    {
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return preg_match('#/(\d+)$#', $path, $matches) === 1 ? $matches[1] : null;
    }
}
