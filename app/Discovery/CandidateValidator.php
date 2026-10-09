<?php

namespace App\Discovery;

use App\Enums\MealType;

/**
 * Hard schema check for one AI-produced recipe candidate. Shared by every
 * claude-backed driver; a malformed candidate returns null and is discarded
 * (or escalated) by the caller.
 */
class CandidateValidator
{
    /**
     * Check one candidate in the recipe shape (see RecipeOutputFormat).
     * Returns the repaired candidate, or every error found as readable
     * strings. Repairs: trims text, drops empty steps and empty
     * alternatives, removes duplicate tools, turns a missing or zero tool
     * count into 1. It does not look for prep text inside a step.
     */
    public function check(mixed $item): CandidateCheck
    {
        if (! is_array($item)) {
            return new CandidateCheck(null, ['candidate: must be a JSON object']);
        }

        $errors = [];

        foreach (['title', 'description'] as $key) {
            if (! is_string($item[$key] ?? null) || trim($item[$key]) === '') {
                $errors[] = "{$key}: must be a non-empty string";
            }
        }

        if (! is_string($item['meal_type'] ?? null) || MealType::tryFrom($item['meal_type']) === null) {
            $errors[] = 'meal_type: must be one of '.implode(', ', array_column(MealType::cases(), 'value'));
        }

        foreach (['prep_minutes', 'cook_minutes', 'servings'] as $key) {
            if (! is_numeric($item[$key] ?? null)) {
                $errors[] = "{$key}: must be a number";
            }
        }

        $sourceUrl = $item['source_url'] ?? null;

        if (! is_string($sourceUrl)
            || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $errors[] = 'source_url: must be a valid http(s) URL';
        }

        $tools = $this->checkTools($item['tools'] ?? null, $errors);
        $ingredients = $this->checkIngredients($item['ingredients'] ?? null, $errors);
        $steps = $this->checkSteps($item['steps'] ?? null, $errors);

        if ($errors !== []) {
            return new CandidateCheck(null, $errors);
        }

        $cuisine = $item['cuisine'] ?? null;

        return new CandidateCheck([
            'title' => trim($item['title']),
            'description' => trim($item['description']),
            'meal_type' => $item['meal_type'],
            'prep_minutes' => (int) $item['prep_minutes'],
            'cook_minutes' => (int) $item['cook_minutes'],
            'servings' => (int) $item['servings'],
            'cuisine' => is_string($cuisine) && trim($cuisine) !== '' ? trim($cuisine) : null,
            'tags' => array_values(array_filter(is_array($item['tags'] ?? null) ? $item['tags'] : [], 'is_string')),
            'source_url' => $sourceUrl,
            'tools' => $tools,
            'ingredients' => $ingredients,
            'steps' => $steps,
        ]);
    }

    /**
     * @param  list<string>  $errors
     * @return list<array{alternatives: list<string>, count: int}>
     */
    private function checkTools(mixed $raw, array &$errors): array
    {
        $tools = [];
        $seen = [];
        $errorsBefore = count($errors);

        foreach (is_array($raw) ? $raw : [] as $i => $tool) {
            if (! is_array($tool) || ! is_array($tool['alternatives'] ?? null)) {
                $errors[] = "tools[{$i}]: must be an object with an alternatives list";

                continue;
            }

            $alternatives = [];

            foreach ($tool['alternatives'] as $j => $word) {
                if (! is_string($word)) {
                    $errors[] = "tools[{$i}].alternatives[{$j}]: must be a string";

                    continue;
                }

                if (trim($word) !== '') {
                    $alternatives[] = trim($word);
                }
            }

            if ($alternatives === []) {
                continue;
            }

            $count = $tool['count'] ?? null;

            if ($count === null || $count === '' || (is_numeric($count) && (float) $count === 0.0)) {
                $count = 1;
            } elseif (! is_numeric($count) || (float) $count < 1 || (float) $count != floor((float) $count)) {
                $errors[] = "tools[{$i}].count: must be a whole number of 1 or more";

                continue;
            }

            $key = collect($alternatives)->map(fn (string $word) => mb_strtolower($word))->unique()->sort()->implode('|');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $tools[] = ['alternatives' => $alternatives, 'count' => (int) $count];
        }

        if ($tools === [] && count($errors) === $errorsBefore) {
            $errors[] = 'tools: at least one tool is required';
        }

        return $tools;
    }

    /**
     * @param  list<string>  $errors
     * @return list<array{qty: ?float, unit: ?string, name: string, prep_note: ?string}>
     */
    private function checkIngredients(mixed $raw, array &$errors): array
    {
        if (! is_array($raw) || $raw === []) {
            $errors[] = 'ingredients: at least one ingredient is required';

            return [];
        }

        $ingredients = [];

        foreach ($raw as $i => $row) {
            if (! is_array($row)) {
                $errors[] = "ingredients[{$i}]: must be an object";

                continue;
            }

            $valid = true;
            $name = $row['name'] ?? null;
            $qty = $row['qty'] ?? null;
            $unit = $row['unit'] ?? null;
            $prepNote = $row['prep_note'] ?? null;

            if (! is_string($name) || trim($name) === '') {
                $errors[] = "ingredients[{$i}].name: must be a non-empty string";
                $valid = false;
            }

            if ($qty !== null && ! is_numeric($qty)) {
                $errors[] = "ingredients[{$i}].qty: must be a number or null";
                $valid = false;
            }

            if ($unit !== null && ! is_string($unit)) {
                $errors[] = "ingredients[{$i}].unit: must be a string or null";
                $valid = false;
            }

            if ($prepNote !== null && ! is_string($prepNote)) {
                $errors[] = "ingredients[{$i}].prep_note: must be a string or null";
                $valid = false;
            }

            if (! $valid) {
                continue;
            }

            $ingredients[] = [
                'qty' => $qty === null ? null : (float) $qty,
                'unit' => $unit,
                'name' => trim($name),
                'prep_note' => $prepNote === null || trim($prepNote) === '' ? null : trim($prepNote),
            ];
        }

        return $ingredients;
    }

    /**
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function checkSteps(mixed $raw, array &$errors): array
    {
        $steps = [];
        $errorsBefore = count($errors);

        foreach (is_array($raw) ? $raw : [] as $i => $step) {
            if (! is_string($step)) {
                $errors[] = "steps[{$i}]: must be a string";

                continue;
            }

            if (trim($step) !== '') {
                $steps[] = trim($step);
            }
        }

        if ($steps === [] && count($errors) === $errorsBefore) {
            $errors[] = 'steps: at least one step is required';
        }

        return $steps;
    }

    /**
     * The pre-shape check (free-text `instructions`), kept for the paths that
     * have not moved to the recipe shape yet (YouTube extraction, requests).
     *
     * @deprecated use check() once the path moves to the recipe shape
     *
     * @return array<string, mixed>|null
     */
    public function validate(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        foreach (['title', 'description', 'instructions'] as $key) {
            if (! is_string($item[$key] ?? null) || trim($item[$key]) === '') {
                return null;
            }
        }

        if (! is_string($item['meal_type'] ?? null) || MealType::tryFrom($item['meal_type']) === null) {
            return null;
        }

        foreach (['prep_minutes', 'cook_minutes', 'servings'] as $key) {
            if (! is_numeric($item[$key] ?? null)) {
                return null;
            }
        }

        if (! is_array($item['tags'] ?? []) || ! is_array($item['ingredients'] ?? null) || $item['ingredients'] === []) {
            return null;
        }

        $ingredients = [];

        foreach ($item['ingredients'] as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? null) || trim($row['name']) === '') {
                return null;
            }

            $qty = $row['qty'] ?? null;
            $unit = $row['unit'] ?? null;
            $note = $row['note'] ?? null;

            if (($qty !== null && ! is_numeric($qty))
                || ($unit !== null && ! is_string($unit))
                || ($note !== null && ! is_string($note))) {
                return null;
            }

            $ingredients[] = [
                'qty' => $qty === null ? null : (float) $qty,
                'unit' => $unit,
                'name' => trim($row['name']),
                'note' => $note,
            ];
        }

        // Every recipe needs a real source (user decision 2026-07-21): a
        // candidate without a valid http(s) source_url is malformed.
        $sourceUrl = $item['source_url'] ?? null;

        if (! is_string($sourceUrl)
            || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        $cuisine = $item['cuisine'] ?? null;

        return [
            'title' => trim($item['title']),
            'description' => trim($item['description']),
            'meal_type' => $item['meal_type'],
            'prep_minutes' => (int) $item['prep_minutes'],
            'cook_minutes' => (int) $item['cook_minutes'],
            'servings' => (int) $item['servings'],
            'instructions' => trim($item['instructions']),
            'cuisine' => is_string($cuisine) && trim($cuisine) !== '' ? trim($cuisine) : null,
            'tags' => array_values(array_filter($item['tags'] ?? [], 'is_string')),
            'source_url' => $sourceUrl,
            'ingredients' => $ingredients,
        ];
    }
}
