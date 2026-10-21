<?php

namespace App\Actions\Recipes;

use App\Enums\IngredientCategory;
use App\Models\Ingredient;
use App\Models\Recipe;

class SyncRecipeIngredients
{
    public function __construct(private ParsePastedIngredients $parser) {}

    /**
     * Replace the recipe's ingredient list with the given rows, finding or
     * creating ingredients by (lowercased) name. A name that still carries a
     * recipe line's amount or prep ("200g tomato", "pinch of salt", "diced
     * onion") is cleaned first, so the ingredient row holds the bare name and
     * the amount lands on the pivot; a quantity or unit the row already gives
     * wins over one read out of the name. A row's prep bowl is kept as given,
     * trimmed, or null when blank.
     *
     * @param  array<int, array{name: string, qty?: mixed, unit?: ?string, note?: ?string, prep_note?: ?string, prep_bowl?: ?string}>  $rows
     */
    public function handle(Recipe $recipe, array $rows): void
    {
        $pivot = [];

        foreach ($rows as $row) {
            $clean = $this->clean($row['name']);

            $ingredient = Ingredient::firstOrCreate(
                ['name' => $clean['name']],
                ['category' => IngredientCategory::Other, 'is_pantry_staple' => false],
            );

            $qty = ($row['qty'] ?? null) === '' ? null : ($row['qty'] ?? null);
            // prep_note (the recipe shape's name for it) lands on the pivot note column.
            $note = ($row['prep_note'] ?? $row['note'] ?? null) ?: null;

            $pivot[$ingredient->id] = [
                'qty' => $qty ?? $clean['qty'],
                'unit' => (($row['unit'] ?? null) ?: null) ?? $clean['unit'],
                'note' => implode(', ', array_filter([$note, $clean['note']], fn (?string $part) => $part !== null && $part !== '')) ?: null,
                'prep_bowl' => trim((string) ($row['prep_bowl'] ?? '')) ?: null,
            ];
        }

        $recipe->ingredients()->sync($pivot);
    }

    /**
     * The bare ingredient name plus whatever amount and prep the raw name
     * carried. A name that is nothing but an amount ("pinch") stays as it is
     * rather than becoming empty.
     *
     * @return array{name: string, qty: ?float, unit: ?string, note: ?string}
     */
    public function clean(string $name): array
    {
        $raw = mb_strtolower(trim($name));
        $parsed = $this->parser->handle($raw)[0] ?? null;

        if ($parsed === null || $parsed['name'] === '') {
            return ['name' => $raw, 'qty' => null, 'unit' => null, 'note' => null];
        }

        return $parsed;
    }
}
