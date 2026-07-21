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
