<?php

namespace App\Discovery;

use App\Actions\Recipes\ParsePastedIngredients;
use Illuminate\Support\Facades\Http;

/**
 * Fallback driver: free TheMealDB API, no key required.
 */
class TheMealDbDriver implements RecipeDiscoveryDriver
{
    private const ENDPOINT = 'https://www.themealdb.com/api/json/v1/1/random.php';

    public function __construct(
        private ParsePastedIngredients $parseIngredients,
    ) {}

    public function discover(int $n): array
    {
        $meals = [];

        for ($i = 0; $i < $n; $i++) {
            $meal = Http::get(self::ENDPOINT)->throw()->json('meals.0');

            if ($meal === null) {
                continue;
            }

            // random.php can repeat; key by id so one run never yields duplicates.
            $meals[$meal['idMeal']] = $meal;
        }

        return array_values(array_map($this->toCandidate(...), $meals));
    }

    /**
     * @param  array<string, mixed>  $meal
     * @return array<string, mixed>
     */
    private function toCandidate(array $meal): array
    {
        $area = trim((string) ($meal['strArea'] ?? ''));

        return [
            'title' => trim((string) $meal['strMeal']),
            'description' => $this->description($meal),
            'meal_type' => 'any', // TheMealDB has no meal-type dimension.
            'prep_minutes' => 0, // TheMealDB publishes no timings or servings;
            'cook_minutes' => 0, // 0 = unknown, review happens at approval time.
            'servings' => 4,
            'instructions' => trim((string) ($meal['strInstructions'] ?? '')),
            'cuisine' => $area !== '' ? $area : null,
            'tags' => $this->tags($meal),
            'source_url' => $this->sourceUrl($meal),
            'ingredients' => $this->ingredientRows($meal),
        ];
    }

    /**
     * @param  array<string, mixed>  $meal
     */
    private function description(array $meal): string
    {
        $parts = array_filter([
            trim((string) ($meal['strArea'] ?? '')),
            trim((string) ($meal['strCategory'] ?? '')),
        ]);

        return $parts === []
            ? 'Recipe from TheMealDB.'
            : implode(' ', $parts).' recipe from TheMealDB.';
    }

    /**
     * @param  array<string, mixed>  $meal
     * @return list<string>
     */
    private function tags(array $meal): array
    {
        $raw = trim((string) ($meal['strTags'] ?? ''));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * The meal's own source or video when the API has one; otherwise its
     * TheMealDB page, which always exists — every recipe carries a source.
     *
     * @param  array<string, mixed>  $meal
     */
    private function sourceUrl(array $meal): string
    {
        foreach (['strSource', 'strYoutube'] as $key) {
            $url = trim((string) ($meal[$key] ?? ''));

            if ($url !== '') {
                return $url;
            }
        }

        return 'https://www.themealdb.com/meal/'.$meal['idMeal'];
    }

    /**
     * strIngredient1..20 / strMeasure1..20 -> pivot-shaped rows, reusing the
     * paste-parser heuristics ("3/4 cup soy sauce" -> qty/unit/name/note).
     *
     * @param  array<string, mixed>  $meal
     * @return list<array{qty: ?float, unit: ?string, name: string, note: ?string}>
     */
    private function ingredientRows(array $meal): array
    {
        $lines = [];

        for ($i = 1; $i <= 20; $i++) {
            $name = trim((string) ($meal["strIngredient{$i}"] ?? ''));

            if ($name === '') {
                continue;
            }

            $measure = trim((string) ($meal["strMeasure{$i}"] ?? ''));

            $lines[] = trim("{$measure} {$name}");
        }

        return $this->parseIngredients->handle(implode("\n", $lines));
    }
}
