<?php

namespace App\Discovery;

use App\Actions\Planning\ComputeTasteProfile;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
use RuntimeException;

/**
 * Primary driver — shells out to the local `claude` CLI (DECISIONS.md #2:
 * `claude -p <prompt> --model sonnet`, exactly like yt2md; no API key).
 */
class AnthropicDriver implements RecipeDiscoveryDriver
{
    /** A rejection theme needs at least this many rejected recipes. */
    private const int REJECTION_THEME_MIN = 2;

    /** Cap on rejection-theme lines so the prompt section stays compact. */
    private const int REJECTION_THEME_CAP = 8;

    /** "Long recipe" rejection-theme threshold (matches DECISIONS.md #6). */
    private const int LONG_RECIPE_MINUTES = 30;

    public function __construct(
        private ClaudeCli $claude,
        private CandidateValidator $validator,
        private ComputeTasteProfile $profile,
    ) {}

    public function discover(int $n): array
    {
        return $this->parseCandidates($this->claude->run($this->prompt($n)));
    }

    private function prompt(int $n): string
    {
        $tasteProfile = $this->tasteProfile();
        $rejections = $this->summarizeRejections();

        $tasteProfileSection = $tasteProfile !== '' ? $tasteProfile : '(none yet)';
        $rejectionSection = $rejections !== '' ? $rejections : '(none yet)';

        return <<<PROMPT
        You are the recipe discovery engine for a household meal planner. Suggest exactly {$n} recipe candidates the household has plausibly never tried.

        Hard constraints for EVERY candidate (healthy + easy):
        - Total time (prep_minutes + cook_minutes) must be 30 minutes or less.
        - 10 ingredients or fewer.
        - Whole-food-leaning: minimally processed ingredients over packaged or ultra-processed ones.

        Taste profile:
        {$tasteProfileSection}

        Themes from previously rejected recipes — avoid these:
        {$rejectionSection}

        Respond with STRICT JSON only: a top-level array of exactly {$n} objects. No prose, no markdown fences, no trailing commentary. Each object has exactly these keys:
        {
          "title": string,
          "description": string (1-2 sentences),
          "meal_type": "breakfast" | "lunch" | "dinner" | "any",
          "prep_minutes": integer,
          "cook_minutes": integer,
          "servings": integer,
          "instructions": string (numbered steps, markdown allowed),
          "cuisine": string or null,
          "tags": array of strings,
          "source_url": null,
          "ingredients": array of {"qty": number or null, "unit": string or null, "name": string, "note": string or null}
        }
        Allowed ingredient units: g, kg, ml, l, tsp, tbsp, cup, oz, lb, count, or null for unitless items.
        PROMPT;
    }

    /**
     * Serialized ComputeTasteProfile output. Empty string on a fresh
     * install, so the prompt keeps its "(none yet)" placeholder.
     */
    protected function tasteProfile(): string
    {
        $profile = $this->profile->handle();

        $describe = fn (array $rated) => collect($rated)
            ->map(fn (float $average, string $name) => "{$name} (avg {$average})")
            ->implode(', ');

        $lines = [];

        if ($profile['cuisines'] !== []) {
            $lines[] = 'Favored cuisines: '.$describe($profile['cuisines']);
        }

        if ($profile['tags'] !== []) {
            $lines[] = 'Favored tags: '.$describe($profile['tags']);
        }

        if ($profile['favoredIngredients'] !== []) {
            $lines[] = 'Favored ingredients: '.implode(', ', $profile['favoredIngredients']);
        }

        if ($profile['avoidedIngredients'] !== []) {
            $lines[] = 'Avoid these ingredients: '.implode(', ', $profile['avoidedIngredients']);
        }

        foreach ($profile['slotPatterns'] as $slot => $rate) {
            $lines[] = sprintf('Habit: %s is skipped %d%% of logged opportunities — favor very fast %s recipes.', $slot, (int) round($rate * 100), $slot);
        }

        return implode("\n", $lines);
    }

    /**
     * DECISIONS.md #3 — rejected titles SUMMARIZED into themes, never listed
     * verbatim. Plain PHP aggregation (no extra AI call): rejected recipes
     * grouped by cuisine, tag, key ingredient, and over-30-minutes, each
     * theme needing at least REJECTION_THEME_MIN members, capped at
     * REJECTION_THEME_CAP lines.
     */
    protected function summarizeRejections(): string
    {
        $rejected = Recipe::query()
            ->where('status', RecipeStatus::Rejected)
            ->with('ingredients')
            ->get();

        if ($rejected->isEmpty()) {
            return '';
        }

        $normalize = fn (?string $value) => mb_strtolower(trim((string) $value));

        // Theme label => rejected-recipe count.
        $themes = collect()
            ->merge($rejected
                ->countBy(fn (Recipe $recipe) => $normalize($recipe->cuisine))
                ->forget('')
                ->mapWithKeys(fn (int $count, string $cuisine) => ["{$cuisine} dishes" => $count]))
            ->merge($rejected
                ->flatMap(fn (Recipe $recipe) => collect($recipe->tags ?? [])->map($normalize)->unique())
                ->countBy()
                ->forget('')
                ->mapWithKeys(fn (int $count, string $tag) => ["recipes tagged \"{$tag}\"" => $count]))
            ->merge($rejected
                ->flatMap(fn (Recipe $recipe) => $recipe->ingredients->pluck('name')->unique())
                ->countBy()
                ->mapWithKeys(fn (int $count, string $ingredient) => ["recipes featuring {$ingredient}" => $count]))
            ->put(
                'recipes over '.self::LONG_RECIPE_MINUTES.' minutes total',
                $rejected->filter(fn (Recipe $recipe) => $recipe->prep_minutes + $recipe->cook_minutes > self::LONG_RECIPE_MINUTES)->count(),
            );

        // Theme = repetition: drop singleton groups, biggest first, capped.
        return $themes
            ->filter(fn (int $count) => $count >= self::REJECTION_THEME_MIN)
            ->sortDesc()
            ->take(self::REJECTION_THEME_CAP)
            ->map(fn (int $count, string $label) => "- {$label} ({$count} rejected)")
            ->implode("\n");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCandidates(string $output): array
    {
        $decoded = json_decode($this->claude->extractJson($output), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException(
                'claude output was not a JSON array of candidates: '.mb_substr(trim($output), 0, 300),
            );
        }

        $candidates = [];

        foreach ($decoded as $item) {
            $candidate = $this->validator->validate($item);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }
}
