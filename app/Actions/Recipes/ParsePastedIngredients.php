<?php

namespace App\Actions\Recipes;

class ParsePastedIngredients
{
    /**
     * Aliases (including plurals) mapped onto the normalized Unit value set.
     * Public so the AI parse path (shaping pass candidate check) normalizes with
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
     * Amount phrases that carry no number ("pinch of salt", "to taste salt",
     * "as required vegetable oil"). They read as a note, never as the name.
     * Longer phrases first so "pinch of" wins over "pinch".
     *
     * @var list<string>
     */
    private const AMOUNT_PHRASES = [
        'as required', 'as needed', 'to taste', 'to serve', 'for serving',
        'pinch of', 'pinch', 'handful of', 'handful', 'bunch of', 'bunch',
    ];

    /**
     * Size words that follow a glued number ("2cm piece ginger", "1 inch
     * knob ginger"): the size and its piece word become the note.
     *
     * @var list<string>
     */
    private const SIZE_WORDS = ['cm', 'mm', 'inch', 'inches', 'in'];

    private const PIECE_WORDS = ['piece', 'pieces', 'knob', 'chunk'];

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

        // Leading amount phrase with no number ("pinch of salt" -> salt / pinch of).
        foreach (self::AMOUNT_PHRASES as $phrase) {
            if (preg_match('/^'.preg_quote($phrase, '/').'\s+(.+)$/i', $rest, $m)) {
                $note = $phrase;
                $rest = $m[1];
                break;
            }
        }

        // Leading quantity: int, decimal, or fraction (1/2), spaced ("200 g")
        // or glued to its unit ("200g").
        if (preg_match('/^(\d+\/\d+|\d+(?:\.\d+)?)\s*(?=[a-z]|\s)(.+)$/i', $rest, $m)) {
            $qty = $this->toDecimal($m[1]);
            $rest = ltrim($m[2]);
        }

        // Optional unit token from the normalized set (plural- and dot-tolerant).
        if (preg_match('/^(\S+)\s+(.+)$/', $rest, $m)) {
            $candidate = mb_strtolower(rtrim($m[1], '.'));

            if (isset(self::UNIT_ALIASES[$candidate])) {
                $unit = self::UNIT_ALIASES[$candidate];
                $rest = $m[2];
            } elseif ($qty !== null && in_array($candidate, self::SIZE_WORDS, true)) {
                // "2cm piece ginger": the size is a note, not a unit, and the
                // number goes with it rather than standing as a quantity.
                $size = $this->formatNumber($qty).(strlen($candidate) > 2 ? ' ' : '').$candidate;
                $qty = null;
                $rest = $m[2];

                if (preg_match('/^(\S+)\s+(.+)$/', $rest, $pm) && in_array(mb_strtolower($pm[1]), self::PIECE_WORDS, true)) {
                    $size .= ' '.mb_strtolower($pm[1]);
                    $rest = $pm[2];
                }

                $note = $size;
            }
        }

        // "2 cloves of garlic", "pinch of salt" already consumed "of"; strip a leftover one.
        $rest = preg_replace('/^of\s+/i', '', $rest);

        // Trailing note after a comma ("chicken thighs, trimmed").
        $trailing = null;

        if (str_contains($rest, ',')) {
            [$rest, $trailing] = array_map('trim', explode(',', $rest, 2));
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

        if ($trailing !== null) {
            $note = ($note !== null && $note !== '' ? $note.', ' : '').$trailing;
        }

        return [
            'qty' => $qty,
            'unit' => $unit,
            'name' => mb_strtolower(implode(' ', $words)),
            'note' => ($note ?? '') !== '' ? $note : null,
        ];
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
