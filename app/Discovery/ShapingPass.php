<?php

namespace App\Discovery;

use App\Support\KitchenToolInventory;

/**
 * Turns one recipe's raw text into the recipe shape (tools, ingredients with
 * prep notes, steps). One model call through the claude CLI, then the shared
 * candidate check; withRetry sends a failed check's errors back to the model
 * once.
 *
 * Raw input keys:
 *   title (required), source_url (required),
 *   ingredients: list of {qty, unit, name, note} (the note is the prep text),
 *   method: the free-text method,
 *   prep_minutes, cook_minutes, servings, meal_type: only when the source
 *     knows them (null or absent = estimate),
 *   description, cuisine, tags: optional, kept as given.
 */
class ShapingPass
{
    public function __construct(
        private ClaudeCli $claude,
        private CandidateValidator $validator,
        private KitchenToolInventory $tools,
        private RetryOnce $retry,
    ) {}

    /**
     * One model call, no retry (scheduled discovery).
     *
     * @param  array<string, mixed>  $raw
     */
    public function once(array $raw): CandidateCheck
    {
        return $this->check($raw, $this->claude->run($this->prompt($raw)));
    }

    /**
     * One model call, and when the check fails one more with the model's own
     * output and the errors (the backfill).
     *
     * @param  array<string, mixed>  $raw
     */
    public function withRetry(array $raw): CandidateCheck
    {
        $checked = $this->retry->run(
            $this->prompt($raw),
            fn (string $output) => $this->check($raw, $output)->errors,
        );

        return $this->check($raw, $checked->output);
    }

    /**
     * Pull the tools, ingredients and steps out of pasted recipe text (the
     * ingredients are in the text, not given separately). The text has no
     * title or source, so the candidate holds only those three parts. No
     * retry: the household is waiting on the form.
     */
    public function fromPastedText(string $text): CandidateCheck
    {
        $output = $this->claude->run($this->pastePrompt($text));
        $decoded = $this->decode($output);

        return $decoded === null
            ? new CandidateCheck(null, ['output: must be one JSON object in the recipe shape'])
            : $this->validator->checkParts($decoded);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function check(array $raw, string $output): CandidateCheck
    {
        $decoded = $this->decode($output);

        if ($decoded === null) {
            return new CandidateCheck(null, ['output: must be one JSON object in the recipe shape']);
        }

        return $this->validator->check($this->withSourceValues($decoded, $raw));
    }

    /**
     * @return ?array<string, mixed> null unless the output holds one JSON object
     */
    private function decode(string $output): ?array
    {
        $decoded = json_decode($this->claude->extractJson($output), true);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
    }

    /**
     * What the source knows wins over the model's estimate or rewording.
     *
     * @param  array<string, mixed>  $shaped
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function withSourceValues(array $shaped, array $raw): array
    {
        foreach (['title', 'source_url', 'description', 'cuisine', 'meal_type', 'prep_minutes', 'cook_minutes', 'servings'] as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '') {
                $shaped[$key] = $raw[$key];
            }
        }

        if (! empty($raw['tags'])) {
            $shaped['tags'] = $raw['tags'];
        }

        return $shaped;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function prompt(array $raw): string
    {
        $kindsSection = RecipeOutputFormat::kindsSection($this->tools->kinds());
        $schema = RecipeOutputFormat::schema((string) ($raw['source_url'] ?? ''));
        $ingredients = $this->ingredientLines($raw['ingredients'] ?? []);
        $method = trim((string) ($raw['method'] ?? ''));

        $known = [];
        foreach (['prep_minutes' => 'prep minutes', 'cook_minutes' => 'cook minutes', 'servings' => 'servings', 'meal_type' => 'meal type'] as $key => $label) {
            if (isset($raw[$key]) && $raw[$key] !== '') {
                $known[] = "- {$label}: {$raw[$key]}";
            }
        }
        $knownSection = $known !== [] ? implode("\n", $known) : '(none)';

        return <<<PROMPT
        You turn one recipe's raw text into a structured recipe. Do not invent ingredients or change the dish.

        Title: {$raw['title']}

        Values the source gives (use these as they are):
        {$knownSection}
        Estimate every prep minutes, cook minutes, servings and meal type value not listed above.

        Ingredient lines:
        {$ingredients}

        Method (free text):
        {$method}

        {$kindsSection}

        Respond with STRICT JSON only: one object, no prose, no markdown fences, with exactly these keys:
        {$schema}
        PROMPT;
    }

    private function pastePrompt(string $text): string
    {
        $kindsSection = RecipeOutputFormat::kindsSection($this->tools->kinds());
        $schema = RecipeOutputFormat::schema();

        return <<<PROMPT
        Below is the text of a recipe someone pasted. It may hold the ingredients and the method together, with headings or other page text around them. Extract the recipe's ingredients, the kitchen tools it needs and its cooking steps from that text. Do not change the dish.

        Pasted text:
        {$text}

        {$kindsSection}

        Respond with STRICT JSON only: one object, no prose, no markdown fences, in this format (the title, description and other keys may be your best reading of the text; only tools, ingredients and steps are used):
        {$schema}
        PROMPT;
    }

    private function ingredientLines(mixed $rows): string
    {
        $lines = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $text = trim(implode(' ', array_filter([
                $row['qty'] ?? null,
                $row['unit'] ?? null,
                $row['name'] ?? null,
            ], fn ($part) => $part !== null && $part !== '')));

            $note = trim((string) ($row['note'] ?? ''));
            $lines[] = '- '.$text.($note !== '' ? ", {$note}" : '');
        }

        return implode("\n", $lines);
    }
}
