<?php

namespace App\Discovery;

use App\Enums\MealType;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Primary driver — shells out to the local `claude` CLI (DECISIONS.md #2:
 * `claude -p <prompt> --model sonnet`, exactly like yt2md; no API key).
 */
class AnthropicDriver implements RecipeDiscoveryDriver
{
    public function discover(int $n): array
    {
        $result = Process::timeout(600)->run([
            $this->binary(), '-p', $this->prompt($n), '--model', 'sonnet',
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'claude CLI failed (exit '.$result->exitCode().'): '
                .trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
            );
        }

        return $this->parseCandidates($result->output());
    }

    private function binary(): string
    {
        $configured = config('mealboard.claude_bin');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $found = (new ExecutableFinder)->find('claude');

        if ($found === null) {
            throw new RuntimeException(
                'claude CLI not found on PATH — install Claude Code or set mealboard.claude_bin.',
            );
        }

        return $found;
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
        $decoded = json_decode($this->extractJson($output), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException(
                'claude output was not a JSON array of candidates: '.mb_substr(trim($output), 0, 300),
            );
        }

        $candidates = [];

        foreach ($decoded as $item) {
            $candidate = $this->validateCandidate($item);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * Strip fences/preamble and isolate the JSON array.
     */
    private function extractJson(string $output): string
    {
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $output, $m)) {
            $output = $m[1];
        }

        $start = strpos($output, '[');
        $end = strrpos($output, ']');

        if ($start === false || $end === false || $end < $start) {
            return trim($output); // Let json_decode fail upstream.
        }

        return substr($output, $start, $end - $start + 1);
    }

    /**
     * Hard schema check; a malformed candidate is discarded individually.
     *
     * @return array<string, mixed>|null
     */
    private function validateCandidate(mixed $item): ?array
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

        $cuisine = $item['cuisine'] ?? null;
        $sourceUrl = $item['source_url'] ?? null;

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
            'source_url' => is_string($sourceUrl) && $sourceUrl !== '' ? $sourceUrl : null,
            'ingredients' => $ingredients,
        ];
    }
}
