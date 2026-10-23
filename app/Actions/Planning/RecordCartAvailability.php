<?php

namespace App\Actions\Planning;

use App\Enums\WalmartAvailability;
use App\Models\WalmartMatch;
use Illuminate\Support\Collection;

/**
 * Fold one signed-in cart import back onto the matches it carted. There is no
 * Walmart API (the signed-in cart is an auth boundary), so the input is what
 * the human pastes: the "Unable to add to Cart" list and the cart's shipping
 * section. Each pasted line is matched to a product by word overlap with its
 * stored name or its URL slug; every other carted match is marked ok.
 */
class RecordCartAvailability
{
    /** Share of the shorter side's words that must overlap. */
    private const MIN_SCORE = 0.75;

    /**
     * @param  Collection<int, WalmartMatch>  $carted  the matches the cart link covered
     * @return array{out-of-stock: list<string>, ship-only: list<string>, ok: int} product names flagged per kind, and how many products were marked ok
     */
    public function handle(Collection $carted, string $outOfStock, string $shipOnly): array
    {
        // Several ingredients can share one product (garlic, garlic cloves):
        // availability belongs to the product, so judge per URL.
        $products = $carted->groupBy('product_url');

        $flags = [];

        // Out of stock wins when a product turns up in both pastes.
        foreach ([
            WalmartAvailability::ShipOnly->value => $shipOnly,
            WalmartAvailability::OutOfStock->value => $outOfStock,
        ] as $kind => $text) {
            foreach (preg_split('/\R/', $text) as $line) {
                $url = $this->bestProduct($line, $products);

                if ($url !== null) {
                    $flags[$url] = WalmartAvailability::from($kind);
                }
            }
        }

        $report = ['out-of-stock' => [], 'ship-only' => [], 'ok' => 0];

        foreach ($products as $url => $matches) {
            $availability = $flags[$url] ?? WalmartAvailability::Ok;

            WalmartMatch::query()->whereKey($matches->modelKeys())->update([
                'availability' => $availability->value,
                'availability_seen_at' => now(),
            ]);

            if ($availability === WalmartAvailability::Ok) {
                $report['ok']++;
            } else {
                $report[$availability->value][] = $matches->first()->product_name;
            }
        }

        return $report;
    }

    /**
     * The product URL a pasted line names, or null when no product clears the
     * bar. Noise lines ("Sold by KoFe", "$3.97", "1 lb") share too few words
     * with any product name to land.
     *
     * @param  Collection<string, Collection<int, WalmartMatch>>  $products
     */
    private function bestProduct(string $line, Collection $products): ?string
    {
        $lineWords = $this->words($line);

        $best = null;
        $bestScore = 0.0;

        foreach ($products as $url => $matches) {
            $slug = str_replace('-', ' ', basename(dirname((string) parse_url($url, PHP_URL_PATH))));

            foreach ([$matches->first()->product_name, $slug] as $title) {
                $score = $this->score($lineWords, $this->words($title));

                if ($score > $bestScore) {
                    [$best, $bestScore] = [$url, $score];
                }
            }
        }

        return $bestScore >= self::MIN_SCORE ? $best : null;
    }

    /**
     * Overlap over the shorter word set, so a short paste ("Organic Green
     * Cabbage") still matches a longer stored title. At least two overlapping
     * words must be non-numeric, or "1 lb" would match every 1 lb product.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function score(array $a, array $b): float
    {
        $shared = array_intersect($a, $b);

        if ($a === [] || $b === [] || count(array_filter($shared, fn (string $word) => ! ctype_digit($word))) < 2) {
            return 0.0;
        }

        return count($shared) / min(count($a), count($b));
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', strtolower($text), $matches);

        return array_values(array_unique($matches[0]));
    }
}
