<?php

namespace App\Discovery;

use RuntimeException;

/**
 * Primary driver — shells out to the local `claude` CLI (DECISIONS.md #2:
 * `claude -p <prompt> --model sonnet`, exactly like yt2md; no API key).
 */
class AnthropicDriver implements RecipeDiscoveryDriver
{
    public function __construct(
        private ClaudeCli $claude,
        private CandidateValidator $validator,
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
     * Taste-profile prompt section — wired to real approval data in step 24.
     */
    protected function tasteProfile(): string
    {
        return '';
    }

    /**
     * DECISIONS.md #3 — rejected titles summarized into themes, not listed
     * verbatim. Real summarization lands in step 24.
     */
    protected function summarizeRejections(): string
    {
        return '';
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
