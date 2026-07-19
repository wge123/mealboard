<?php

namespace App\Actions\Recipes;

use App\Discovery\ClaudeCli;
use App\Enums\Unit;
use RuntimeException;

/**
 * AI-backed paste parser (step 21): sends the raw pasted text through the
 * local claude CLI (DECISIONS.md #2) and returns schema-checked ingredient
 * rows plus the instructions block. Throws on any CLI or shape failure —
 * the caller (RecipeCreate) falls back to the heuristic parser visibly.
 */
class ParsePastedRecipeWithAi
{
    public function __construct(private ClaudeCli $claude) {}

    /**
     * @return array{ingredients: array<int, array{qty: ?float, unit: ?string, name: string, note: ?string}>, instructions: string}
     */
    public function handle(string $text): array
    {
        $output = $this->claude->run($this->prompt($text));
        $decoded = json_decode($this->claude->extractJson($output), true);

        if (! is_array($decoded) || ! is_array($decoded['ingredients'] ?? null) || ! is_string($decoded['instructions'] ?? null)) {
            throw new RuntimeException('AI parse returned malformed JSON (expected {"ingredients": [...], "instructions": "..."}).');
        }

        $ingredients = [];

        foreach ($decoded['ingredients'] as $row) {
            $ingredients[] = $this->normalizeRow($row);
        }

        return [
            'ingredients' => $ingredients,
            'instructions' => trim($decoded['instructions']),
        ];
    }

    /**
     * @return array{qty: ?float, unit: ?string, name: string, note: ?string}
     */
    private function normalizeRow(mixed $row): array
    {
        if (! is_array($row) || ! is_string($row['name'] ?? null) || trim($row['name']) === '') {
            throw new RuntimeException('AI parse returned an ingredient without a name.');
        }

        $qty = $row['qty'] ?? null;
        $unit = $row['unit'] ?? null;
        $note = $row['note'] ?? null;

        if (($qty !== null && ! is_numeric($qty))
            || ($unit !== null && ! is_string($unit))
            || ($note !== null && ! is_string($note))) {
            throw new RuntimeException("AI parse returned a malformed ingredient row for \"{$row['name']}\".");
        }

        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        $unit = $unit !== null ? mb_strtolower(trim($unit)) : null;
        $normalizedUnit = $unit !== null ? (ParsePastedIngredients::UNIT_ALIASES[$unit] ?? null) : null;

        // An off-menu unit ("clove", "bunch") is demoted into the note rather
        // than silently dropped.
        if ($unit !== null && $normalizedUnit === null) {
            $note = $unit.($note !== null ? ', '.$note : '');
        }

        return [
            'qty' => $qty !== null ? (float) $qty : null,
            'unit' => $normalizedUnit,
            'name' => mb_strtolower(trim($row['name'])),
            'note' => $note,
        ];
    }

    private function prompt(string $text): string
    {
        $units = implode(', ', array_column(Unit::cases(), 'value'));

        return <<<PROMPT
        You convert a raw pasted recipe (any format — ingredient lists, full blog posts, transcripts) into structured data for a meal planner form.

        Pasted text:
        \"\"\"
        {$text}
        \"\"\"

        Respond with STRICT JSON only: a single top-level object, no prose, no markdown fences, no trailing commentary. Exactly these keys:
        {
          "ingredients": array of {"qty": number or null, "unit": string or null, "name": string, "note": string or null},
          "instructions": string (numbered steps; empty string if the text contains no instructions)
        }
        Allowed ingredient units: {$units}, or null for unitless items. Lowercase ingredient names. Prep like "diced" or "juiced" belongs in note, not name.
        PROMPT;
    }
}
