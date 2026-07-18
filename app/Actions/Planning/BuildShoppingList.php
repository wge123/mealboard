<?php

namespace App\Actions\Planning;

use App\Enums\IngredientCategory;
use App\Enums\MealPlanStatus;
use App\Models\Ingredient;
use App\Models\MealPlan;
use LogicException;

class BuildShoppingList
{
    /**
     * Convertible unit families: unit => factor relative to the family's base
     * unit. Units outside these families (oz, lb, count, null) only merge with
     * themselves — cups and grams of the same ingredient stay separate lines.
     */
    private const array FAMILIES = [
        'spoon' => ['tsp' => 1, 'tbsp' => 3, 'cup' => 48],
        'mass' => ['g' => 1, 'kg' => 1000],
        'volume' => ['ml' => 1, 'l' => 1000],
    ];

    /**
     * Build the merged shopping list for a locked (or completed) week.
     *
     * @return array<string, list<array{name: string, qty: float|null, unit: string|null, notes: list<string>}>>
     */
    public function handle(MealPlan $plan, bool $includeStaples = false): array
    {
        if ($plan->status === MealPlanStatus::Draft) {
            throw new LogicException('Only locked weeks have a shopping list.');
        }

        $plan->load('plannedMeals.recipe.ingredients');

        $lines = [];

        foreach ($plan->plannedMeals as $meal) {
            foreach ($meal->recipe->ingredients as $ingredient) {
                if (! $includeStaples && $ingredient->is_pantry_staple) {
                    continue;
                }

                $this->accumulate($lines, $ingredient);
            }
        }

        return $this->group($lines);
    }

    /**
     * @param  array<string, array<string, mixed>>  $lines
     */
    private function accumulate(array &$lines, Ingredient $ingredient): void
    {
        $unit = $ingredient->pivot->unit;
        $family = $this->family($unit);

        // Family units share a merge bucket; everything else merges per unit.
        $key = $ingredient->name.'|'.($family ?? $unit ?? '');

        $lines[$key] ??= [
            'name' => $ingredient->name,
            'category' => $ingredient->category,
            'family' => $family,
            'unit' => $unit,
            'qty' => null,
            'notes' => [],
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
     * @return array<string, list<array{name: string, qty: float|null, unit: string|null, notes: list<string>}>>
     */
    private function group(array $lines): array
    {
        $grouped = [];

        foreach ($lines as $line) {
            $grouped[$line['category']->value][] = $this->present($line);
        }

        $order = array_flip(array_column(IngredientCategory::cases(), 'value'));
        uksort($grouped, fn (string $a, string $b) => $order[$a] <=> $order[$b]);

        foreach ($grouped as &$items) {
            usort($items, fn (array $a, array $b) => $a['name'] <=> $b['name']);
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{name: string, qty: float|null, unit: string|null, notes: list<string>}
     */
    private function present(array $line): array
    {
        $qty = $line['qty'];
        $unit = $line['unit'];

        if ($line['family'] !== null && $qty !== null) {
            [$qty, $unit] = $this->displayUnit($line['family'], $qty);
        }

        return [
            'name' => $line['name'],
            'qty' => $qty === null ? null : round($qty, 2),
            'unit' => $unit,
            'notes' => $line['notes'],
        ];
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
