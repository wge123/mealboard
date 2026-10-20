<?php

namespace App\Actions\Recipes;

use App\Support\Text;

/**
 * Restructuring of one source ingredient line by code (GLOSSARY: Restructuring,
 * ADR 0004): amount, unit, name and prep note. A stricter cousin of the
 * pasted-text parser: it never guesses. An amount the line does not state
 * stays null; a unit word becomes an allowed unit only when exactly equal
 * (the shared alias table), otherwise the source's word stays in the name.
 * Text after the first comma, a trailing "(...)" and a trailing "to taste"
 * become the prep note, so no word of the line is lost.
 */
class RestructureIngredientLine
{
    private const FRACTIONS = [
        '¼' => '1/4', '½' => '1/2', '¾' => '3/4', '⅓' => '1/3', '⅔' => '2/3',
        '⅛' => '1/8', '⅜' => '3/8', '⅝' => '5/8', '⅞' => '7/8',
        '⅕' => '1/5', '⅖' => '2/5', '⅗' => '3/5', '⅘' => '4/5', '⅙' => '1/6', '⅚' => '5/6',
    ];

    /** Words for a sized package whose size a page may give in brackets. */
    private const CONTAINERS = ['can', 'tin', 'jar', 'package', 'packet', 'bag', 'box', 'bottle', 'carton', 'pouch', 'tub'];

    /**
     * @return array{qty: ?float, unit: ?string, name: string, prep_note: ?string}
     */
    public function handle(string $line): array
    {
        $rest = Text::collapseWhitespace($line);
        $notes = [];

        // Text after the first comma is the prep note; a bracketed note just before it comes first.
        $afterComma = null;

        if (str_contains($rest, ',') && ! $this->commaIsInsideBrackets($rest)) {
            [$rest, $afterComma] = array_map('trim', explode(',', $rest, 2));
        }

        if ($bracket = $this->trailingBracket($rest)) {
            [$rest, $note] = $bracket;
            $notes[] = $note;
        }

        if ($afterComma !== null && $afterComma !== '') {
            $notes[] = $afterComma;
        }

        if (preg_match('/^(.*\S)\s+to taste$/i', $rest, $m)) {
            $rest = $m[1];
            array_unshift($notes, 'to taste');
        }

        [$qty, $rest] = $this->takeAmount($rest);
        $unit = null;

        // "1 can (400 g) tomatoes": the page's own exact amount, with its container word kept as a note.
        if ($qty === 1.0 && ($boxed = $this->takeBoxedAmount($rest)) !== null) {
            [$qty, $unit, $rest, $container] = $boxed;
            array_unshift($notes, "1 {$container}");
        } elseif ($qty !== null && preg_match('/^(\S+)\s+(\S.*)$/u', $rest, $m)) {
            $word = mb_strtolower(rtrim($m[1], '.'));

            if (isset(ParsePastedIngredients::UNIT_ALIASES[$word])) {
                $unit = ParsePastedIngredients::UNIT_ALIASES[$word];
                $rest = $m[2];
            }
        }

        $name = mb_strtolower(trim(preg_replace('/^of\s+/i', '', $rest)));

        return [
            'qty' => $qty,
            'unit' => $unit,
            'name' => $name,
            'prep_note' => $notes === [] ? null : implode(', ', $notes),
        ];
    }

    /**
     * Whether a restructured row is its source line and nothing else: no word
     * or number of the line is missing from the row's name, prep note, unit
     * (compared through the unit alias table, so "tablespoons" matches "tbsp")
     * or amount, and the row has no word or number the line lacks. Fractions
     * are compared by value ("½", "1/2" and 0.5 agree). The joining "of" of
     * "1 cup of rice" is the one word restructuring may drop.
     *
     * @param  array{qty: ?float, unit: ?string, name: string, prep_note: ?string}  $row
     */
    public function isLineForLine(string $line, array $row): bool
    {
        if ($row['name'] === '') {
            return false;
        }

        [$sourceNumbers, $sourceWords] = $this->numbersAndWords($line);
        [$rowNumbers, $rowWords] = $this->numbersAndWords($row['name'].' '.($row['prep_note'] ?? ''));

        if ($row['qty'] !== null) {
            $rowNumbers[] = $this->numberKey((float) $row['qty']);
        }

        if ($row['unit'] !== null) {
            $rowWords[] = $this->canonicalWord($row['unit']);
        }

        // Only the row's name and prep note must come from the line's words;
        // the amount and unit are checked as numbers and canonical unit words.
        $sourceWords = array_diff($sourceWords, ['of']);

        return array_diff($sourceWords, $rowWords) === []
            && array_diff($sourceNumbers, $rowNumbers) === []
            && array_diff($rowWords, $sourceWords, ['of']) === []
            && array_diff($rowNumbers, $sourceNumbers) === [];
    }

    /**
     * The numbers (by value) and the words (unit words in their allowed form) of a text.
     *
     * @return array{list<string>, list<string>}
     */
    private function numbersAndWords(string $text): array
    {
        $text = mb_strtolower($text);

        foreach (self::FRACTIONS as $glyph => $fraction) {
            $text = preg_replace('/(\d)\s*'.$glyph.'/u', '$1 '.$fraction, $text);
            $text = str_replace($glyph, ' '.$fraction.' ', $text);
        }

        $numbers = [];
        $text = preg_replace_callback('/(\d+)\s+(\d+)\/(\d+)|(\d+)\/(\d+)|(\d+(?:\.\d+)?)/', function (array $m) use (&$numbers) {
            $numbers[] = $this->numberKey(match (true) {
                ($m[1] ?? '') !== '' => (int) $m[1] + (int) $m[2] / max(1, (int) $m[3]),
                ($m[4] ?? '') !== '' => (int) $m[4] / max(1, (int) $m[5]),
                default => (float) $m[6],
            });

            return ' ';
        }, $text);

        preg_match_all('/\p{L}+/u', $text, $words);

        return [$numbers, array_map($this->canonicalWord(...), $words[0])];
    }

    private function canonicalWord(string $word): string
    {
        return ParsePastedIngredients::UNIT_ALIASES[$word] ?? $word;
    }

    private function numberKey(float $number): string
    {
        return (string) round($number, 3);
    }

    /**
     * "1¼" becomes "1 1/4" and a lone "¼" "1/4", in the leading amount only:
     * a fraction glyph inside a note stays as the source wrote it.
     */
    private function asciiFractions(string $text): string
    {
        $glyphs = implode('', array_keys(self::FRACTIONS));

        if (! preg_match('/^([\d\s.\/'.$glyphs.']+)(.*)$/u', $text, $m)) {
            return $text;
        }

        $amount = $m[1];

        foreach (self::FRACTIONS as $glyph => $fraction) {
            $amount = preg_replace('/(\d)\s*'.$glyph.'/u', '$1 '.$fraction, $amount);
            $amount = str_replace($glyph, $fraction, $amount);
        }

        return $amount.$m[2];
    }

    /**
     * @return array{?float, string}
     */
    private function takeAmount(string $text): array
    {
        $text = $this->asciiFractions($text);

        if (! preg_match('/^(?:(\d+) (\d+)\/(\d+)|(\d+)\/(\d+)|(\d+(?:\.\d+)?))(?=\s|[a-z]|$)\s*(.*)$/iu', $text, $m)) {
            return [null, $text];
        }

        $qty = match (true) {
            ($m[1] ?? '') !== '' => (int) $m[1] + (int) $m[2] / max(1, (int) $m[3]),
            ($m[4] ?? '') !== '' => (int) $m[4] / max(1, (int) $m[5]),
            default => (float) $m[6],
        };

        return [round($qty, 3), $m[7]];
    }

    /**
     * What follows a count of one when the page gives the container's exact
     * size in brackets, as "can (400 g) tomatoes" or "(14-ounce) can tomatoes":
     * the bracketed amount and its allowed unit, the rest of the line, and the
     * container word. Null for any other shape (a bracket that is not just an
     * amount and an allowed unit, a word that is not a container), which then
     * stays as the page wrote it. Only a count of one is taken, since 2 cans of
     * 400 g would need arithmetic the page never did.
     *
     * @return ?array{float, string, string, string} [amount, unit, rest of the line, container]
     */
    private function takeBoxedAmount(string $text): ?array
    {
        $size = '\(\s*(\d+(?:\.\d+)?)\s*-?\s*([a-z]+)\.?\s*\)';
        $containers = implode('|', self::CONTAINERS);

        if (preg_match("/^({$containers})\s*{$size}\s*(\S.*)$/iu", $text, $m)) {
            [, $container, $amount, $word, $rest] = $m;
        } elseif (preg_match("/^{$size}\s*({$containers})\s+(\S.*)$/iu", $text, $m)) {
            [, $amount, $word, $container, $rest] = $m;
        } else {
            return null;
        }

        $unit = ParsePastedIngredients::UNIT_ALIASES[mb_strtolower($word)] ?? null;

        if ($unit === null) {
            return null;
        }

        return [(float) $amount, $unit, mb_strtolower(trim(preg_replace('/^of\s+/i', '', $rest))), mb_strtolower($container)];
    }

    /**
     * A closing "(...)" at the end of the text, with its brackets balanced.
     *
     * @return ?array{string, string} [text before, note inside]
     */
    private function trailingBracket(string $text): ?array
    {
        if (! str_ends_with($text, ')')) {
            return null;
        }

        $open = $this->walkBrackets($text)['pairs'][strlen($text) - 1] ?? null;

        if ($open === null) {
            return null;
        }

        $before = trim(substr($text, 0, $open));
        $inside = trim(substr($text, $open + 1, -1));

        return $before === '' || $inside === '' ? null : [$before, $inside];
    }

    /**
     * Whether the first comma sits inside brackets ("onion (chopped, fine)"),
     * where it belongs to a bracketed note rather than starting the prep note.
     */
    private function commaIsInsideBrackets(string $text): bool
    {
        return ($this->walkBrackets($text)['commaDepth'] ?? 0) > 0;
    }

    /**
     * The one walk over a text's round brackets: the position of the "(" that
     * each ")" closes (a stray ")" closes nothing), and how deep the first
     * comma sits.
     *
     * @return array{pairs: array<int, int>, commaDepth: ?int}
     */
    private function walkBrackets(string $text): array
    {
        $open = [];
        $pairs = [];
        $commaDepth = null;

        foreach (str_split($text) as $i => $char) {
            if ($char === '(') {
                $open[] = $i;
            } elseif ($char === ')' && $open !== []) {
                $pairs[$i] = array_pop($open);
            } elseif ($char === ',' && $commaDepth === null) {
                $commaDepth = count($open);
            }
        }

        return ['pairs' => $pairs, 'commaDepth' => $commaDepth];
    }
}
