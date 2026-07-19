<?php

namespace App\Actions\Recipes;

class ParsePastedIngredients
{
    /**
     * Aliases (including plurals) mapped onto the normalized Unit value set.
     * Public so the AI parse path (ParsePastedRecipeWithAi) normalizes with
     * the same table.
     *
     * @var array<string, string>
     */
    public const UNIT_ALIASES = [
        'g' => 'g', 'gram' => 'g', 'grams' => 'g',
        'kg' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg',
        'ml' => 'ml', 'milliliter' => 'ml', 'milliliters' => 'ml', 'millilitre' => 'ml', 'millilitres' => 'ml',
        'l' => 'l', 'liter' => 'l', 'liters' => 'l', 'litre' => 'l', 'litres' => 'l',
        'tsp' => 'tsp', 'teaspoon' => 'tsp', 'teaspoons' => 'tsp',
        'tbsp' => 'tbsp', 'tablespoon' => 'tbsp', 'tablespoons' => 'tbsp',
        'cup' => 'cup', 'cups' => 'cup',
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
        'lb' => 'lb', 'lbs' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
        'count' => 'count',
    ];

    /**
     * Prep words that read as a note rather than part of the ingredient name
     * ("2 cups diced onion" -> onion, note "diced").
     *
     * @var list<string>
     */
    private const PREP_WORDS = [
        'diced', 'chopped', 'minced', 'sliced', 'grated', 'shredded', 'crushed',
        'peeled', 'cubed', 'melted', 'softened', 'beaten', 'rinsed', 'drained',
        'torn', 'cooked', 'toasted', 'frozen', 'fresh',
    ];

    /**
     * Heuristic line parser. The AI-backed parse arrives in step 21.
     *
     * @return array<int, array{qty: ?float, unit: ?string, name: string, note: ?string}>
     */
    public function handle(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim(ltrim(trim($line), '-*• '));

            if ($line === '') {
                continue;
            }

            $rows[] = $this->parseLine($line);
        }

        return $rows;
    }

    /**
     * @return array{qty: ?float, unit: ?string, name: string, note: ?string}
     */
    private function parseLine(string $line): array
    {
        $qty = null;
        $unit = null;
        $note = null;
        $rest = $line;

        // Leading quantity: int, decimal, or fraction (1/2).
        if (preg_match('/^(\d+\/\d+|\d+(?:\.\d+)?)\s+(.+)$/', $rest, $m)) {
            $qty = $this->toDecimal($m[1]);
            $rest = $m[2];
        }

        // Optional unit token from the normalized set (plural- and dot-tolerant).
        if (preg_match('/^(\S+)\s+(.+)$/', $rest, $m)) {
            $candidate = mb_strtolower(rtrim($m[1], '.'));

            if (isset(self::UNIT_ALIASES[$candidate])) {
                $unit = self::UNIT_ALIASES[$candidate];
                $rest = $m[2];
            }
        }

        // Trailing note after a comma ("chicken thighs, trimmed").
        if (str_contains($rest, ',')) {
            [$rest, $note] = array_map('trim', explode(',', $rest, 2));
        }

        // Leading prep words become the note ("diced onion" -> onion / diced).
        $words = preg_split('/\s+/', trim($rest));
        $prep = [];

        while (count($words) > 1 && in_array(mb_strtolower($words[0]), self::PREP_WORDS, true)) {
            $prep[] = mb_strtolower(array_shift($words));
        }

        if ($prep !== []) {
            $note = implode(' ', $prep).($note !== null && $note !== '' ? ', '.$note : '');
        }

        return [
            'qty' => $qty,
            'unit' => $unit,
            'name' => mb_strtolower(implode(' ', $words)),
            'note' => ($note ?? '') !== '' ? $note : null,
        ];
    }

    private function toDecimal(string $value): float
    {
        if (str_contains($value, '/')) {
            [$numerator, $denominator] = explode('/', $value, 2);

            return round((int) $numerator / max(1, (int) $denominator), 2);
        }

        return (float) $value;
    }
}
