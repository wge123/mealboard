<?php

namespace App\Discovery;

use App\Support\KitchenToolInventory;

/**
 * Turns one recipe's raw text into the recipe shape (tools, ingredients with
 * prep notes, steps). One model call through the claude CLI, then the shared
 * candidate check; with $retry the failed check's errors go back to the model
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
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public function handle(array $raw, bool $retry = false): CandidateCheck
    {
        $prompt = $this->prompt($raw);
        $check = $this->attempt($raw, $prompt);

        if ($check->passes() || ! $retry) {
            return $check;
        }

        $errors = implode("\n", array_map(fn (string $error) => "- {$error}", $check->errors));

        return $this->attempt($raw, <<<PROMPT
        {$prompt}

        Your previous answer failed these checks:
        {$errors}

        Answer again with the full corrected JSON object.
        PROMPT);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function attempt(array $raw, string $prompt): CandidateCheck
    {
        $decoded = json_decode($this->claude->extractJson($this->claude->run($prompt)), true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return new CandidateCheck(null, ['output: must be one JSON object in the recipe shape']);
        }

        return $this->validator->check($this->withSourceValues($decoded, $raw));
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
        $kinds = $this->tools->kinds()->implode(', ');
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

        Kitchen tool words you may use:
        {$kinds}

        Pick tool words from that list; only use another word if none fits.

        Respond with STRICT JSON only: one object, no prose, no markdown fences, with exactly these keys:
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
