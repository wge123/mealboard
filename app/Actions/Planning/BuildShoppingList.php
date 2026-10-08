<?php

namespace App\Actions\Planning;

use App\Enums\IngredientCategory;
use App\Enums\MealPlanStatus;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Support\CleanIngredientKeywords;
use App\Support\WalmartCartLink;
use LogicException;

class BuildShoppingList
{
    /**
     * Convertible unit families: unit => factor relative to the family's base
     * unit. Units outside these families (oz, lb, count, null) only merge with
     * themselves — cups and grams of the same ingredient stay separate lines.
     */
    private const string WALMART_SEARCH = 'https://www.walmart.com/search?q=';

    private const array FAMILIES = [
        'spoon' => ['tsp' => 1, 'tbsp' => 3, 'cup' => 48],
        'mass' => ['g' => 1, 'kg' => 1000],
        'volume' => ['ml' => 1, 'l' => 1000],
    ];

    public function __construct(
        private CleanIngredientKeywords $cleanKeywords,
        private WalmartCartLink $cartLink,
    ) {}

    /**
     * Build the merged shopping list for a locked (or completed) week.
     *
     * A line's key is what the page sends when the line is checked; its
     * product_url is the product match, if any, its keywords the cleaned
     * search keywords, and its search_url a Walmart search on them.
     *
     * The buy list: one entry per ingredient still to buy (name, keywords,
     * product_url), leaving out pantry staples and any ingredient whose every
     * line is checked. The cart link adds the buy list's matched products,
     * and is null when none of them is matched.
     *
     * @return array{lines: array<string, list<array{key: string, name: string, qty: float|null, unit: string|null, notes: list<string>, label: string, checked: bool, product_url: string|null, keywords: string, search_url: string}>>, buy_list: list<array{name: string, keywords: string, product_url: string|null}>, cart_link: string|null}
     */
    public function handle(MealPlan $plan, bool $includeStaples = false): array
    {
        if ($plan->status === MealPlanStatus::Draft) {
            throw new LogicException('Only locked weeks have a shopping list.');
        }

        $plan->load('plannedMeals.recipe.ingredients.walmartMatch');

        $lines = [];

        foreach ($plan->plannedMeals as $meal) {
            foreach ($meal->recipe->ingredients as $ingredient) {
                if (! $includeStaples && $ingredient->is_pantry_staple) {
                    continue;
                }

                $this->accumulate($lines, $ingredient);
            }
        }

        $grouped = $this->group($lines, $plan->checked_items ?? []);

        $staples = collect($lines)->where('pantry_staple', true)->pluck('name')->all();

        $buyList = $this->buyList($grouped, $staples);

        return [
            'lines' => $grouped,
            'buy_list' => $buyList,
            'cart_link' => $this->cartLink->handle(
                array_values(array_filter(array_column($buyList, 'product_url'))),
            ),
        ];
    }

    /**
     * Walk the lines in store-flow order, keeping each ingredient once while
     * any of its lines is still unchecked.
     *
     * @param  array<string, list<array{key: string, name: string, qty: float|null, unit: string|null, notes: list<string>, label: string, checked: bool, product_url: string|null, keywords: string, search_url: string}>>  $grouped
     * @param  list<string>  $staples
     * @return list<array{name: string, keywords: string, product_url: string|null}>
     */
    private function buyList(array $grouped, array $staples): array
    {
        $entries = [];

        foreach ($grouped as $lines) {
            foreach ($lines as $line) {
                if ($line['checked'] || in_array($line['name'], $staples, true)) {
                    continue;
                }

                $entries[$line['name']] ??= [
                    'name' => $line['name'],
                    'keywords' => $line['keywords'],
                    'product_url' => $line['product_url'],
                ];
            }
        }

        return array_values($entries);
    }

    /**
     * @param  array<string, array<string, mixed>>  $lines
     */
    private function accumulate(array &$lines, Ingredient $ingredient): void
    {
        $unit = $ingredient->pivot->unit;
        $family = $this->family($unit);

        // Family units share a merge bucket; everything else merges per unit.
        // The bucket, not the display unit, is the line's key, so a checked
        // line stays checked when its amount crosses a display threshold.
        $key = $ingredient->name.'|'.($family ?? $unit ?? '');

        $lines[$key] ??= [
            'key' => $key,
            'name' => $ingredient->name,
            'category' => $ingredient->category,
            'pantry_staple' => $ingredient->is_pantry_staple,
            'family' => $family,
            'unit' => $unit,
            'qty' => null,
            'notes' => [],
            'product_url' => $ingredient->walmartMatch?->product_url,
        ];

        if ($ingredient->pivot->qty !== null) {
            $qty = (float) $ingredient->pivot->qty;
            $base = $family === null ? $qty : $qty * self::FAMILIES[$family][$unit];

            $lines[$key]['qty'] = ($lines[$key]['qty'] ?? 0.0) + $base;
        }

        $note = $ingredient->pivot->note;

        if ($note !== null && ! in_array($note, $lines[$key]['notes'], true)) {
            $lines[$key]['notes'][] = $note;
        }
    }

    private function family(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        foreach (self::FAMILIES as $family => $factors) {
            if (isset($factors[$unit])) {
                return $family;
            }
        }

        return null;
    }

    /**
     * Group by category (store-flow order: the IngredientCategory case order),
     * sorting lines by name within each category.
     *
     * @param  array<string, array<string, mixed>>  $lines
     * @param  list<string>  $checked
     * @return array<string, list<array{key: string, name: string, qty: float|null, unit: string|null, notes: list<string>, label: string, checked: bool, product_url: string|null, keywords: string, search_url: string}>>
     */
    private function group(array $lines, array $checked): array
    {
        $grouped = [];

        foreach ($lines as $line) {
            $grouped[$line['category']->value][] = $this->present($line, $checked);
        }

        $order = array_flip(array_column(IngredientCategory::cases(), 'value'));
        uksort($grouped, fn (string $a, string $b) => $order[$a] <=> $order[$b]);

        foreach ($grouped as &$categoryLines) {
            usort($categoryLines, fn (array $a, array $b) => $a['name'] <=> $b['name']);
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $checked
     * @return array{key: string, name: string, qty: float|null, unit: string|null, notes: list<string>, label: string, checked: bool, product_url: string|null, keywords: string, search_url: string}
     */
    private function present(array $line, array $checked): array
    {
        $qty = $line['qty'];
        $unit = $line['unit'];

        if ($line['family'] !== null && $qty !== null) {
            [$qty, $unit] = $this->displayUnit($line['family'], $qty);
        }

        $qty = $qty === null ? null : round($qty, 2);
        $key = $line['key'];
        $keywords = $this->cleanKeywords->handle($line['name']);

        return [
            'key' => $key,
            'name' => $line['name'],
            'qty' => $qty,
            'unit' => $unit,
            'notes' => $line['notes'],
            'label' => $this->label($line['name'], $qty, $unit),
            'checked' => in_array($key, $checked, true),
            'product_url' => $line['product_url'],
            'keywords' => $keywords,
            'search_url' => self::WALMART_SEARCH.urlencode($keywords),
        ];
    }

    /** "1.5 kg flour"; a count unit is left out ("3 carrots", not "3 count carrots"). */
    private function label(string $name, ?float $qty, ?string $unit): string
    {
        $parts = [];

        if ($qty !== null) {
            $parts[] = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        }

        if ($unit !== null && $unit !== Unit::Count->value) {
            $parts[] = $unit;
        }

        $parts[] = $name;

        return implode(' ', $parts);
    }

    /**
     * Largest family unit that keeps the quantity at or above one (500 ml
     * stays ml, 1500 ml becomes 1.5 l).
     *
     * @return array{0: float, 1: string}
     */
    private function displayUnit(string $family, float $baseQty): array
    {
        $factors = self::FAMILIES[$family];
        arsort($factors);

        foreach ($factors as $unit => $factor) {
            if ($baseQty >= $factor) {
                return [$baseQty / $factor, $unit];
            }
        }

        // Below one of even the smallest unit (e.g. 0.5 tsp).
        return [$baseQty, array_key_last($factors)];
    }
}
