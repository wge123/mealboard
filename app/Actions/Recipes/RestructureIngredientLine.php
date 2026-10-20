<?php

namespace App\Actions\Recipes;

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

    /**
     * @return array{qty: ?float, unit: ?string, name: string, prep_note: ?string}
     */
    public function handle(string $line): array
    {
        $rest = $this->normalize($line);
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

        if ($qty !== null && preg_match('/^(\S+)\s+(\S.*)$/u', $rest, $m)) {
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

    private function normalize(string $line): string
    {
        return trim(preg_replace('/\s+/u', ' ', $line));
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
     * A closing "(...)" at the end of the text, with its brackets balanced.
     *
     * @return ?array{string, string} [text before, note inside]
     */
    private function trailingBracket(string $text): ?array
    {
        if (! str_ends_with($text, ')')) {
            return null;
        }

        $depth = 0;

        for ($i = strlen($text) - 1; $i >= 0; $i--) {
            $depth += match ($text[$i]) {
                ')' => 1,
                '(' => -1,
                default => 0,
            };

            if ($depth === 0) {
                $before = trim(substr($text, 0, $i));
                $inside = trim(substr($text, $i + 1, -1));

                return $before === '' || $inside === '' ? null : [$before, $inside];
            }
        }

        return null;
    }

    /**
     * Whether the first comma sits inside brackets ("onion (chopped, fine)"),
     * where it belongs to a bracketed note rather than starting the prep note.
     */
    private function commaIsInsideBrackets(string $text): bool
    {
        $depth = 0;

        foreach (str_split($text) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',') {
                return $depth > 0;
            }
        }

        return false;
    }
}
