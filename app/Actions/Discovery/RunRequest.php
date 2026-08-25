<?php

namespace App\Actions\Discovery;

use App\Actions\Recipes\CreateRecipe;
use App\Discovery\AnthropicDriver;
use App\Discovery\NearDuplicateFilter;
use App\Enums\RecipeSource;
use App\Enums\RequestStatus;
use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;
use App\Models\RecipeRequest;
use Throwable;

/**
 * Run one household request end to end: search YouTube for it, ask claude for
 * it, and store whatever survives as pending recipes tagged with the request.
 *
 * Lane failures are independent. One lane dying still lets the other deliver
 * candidates, and the request completes carrying the failure text. Only a
 * request in which EVERY lane died is a failed request, because that is the
 * case where the household asked for something and got nothing back.
 */
class RunRequest
{
    public function __construct(
        private SearchYouTube $search,
        private ClassifyDiscoveredVideos $classify,
        private ExtractRecipeFromVideo $extract,
        private AnthropicDriver $anthropic,
        private NearDuplicateFilter $duplicates,
        private CreateRecipe $createRecipe,
    ) {}

    /**
     * @return int number of pending recipes created
     */
    public function handle(RecipeRequest $request): int
    {
        $request->update(['status' => RequestStatus::Running, 'error' => null]);

        $candidates = [];
        $errors = [];
        $lanes = 0;

        foreach (['youtube' => fn () => $this->youtubeLane($request), 'claude' => fn () => $this->claudeLane($request)] as $lane => $run) {
            $lanes++;

            try {
                $candidates = [...$candidates, ...$run()];
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$lane}: ".$e->getMessage();
            }
        }

        if (count($errors) === $lanes) {
            $request->update([
                'status' => RequestStatus::Failed,
                'error' => implode("\n", $errors),
                'completed_at' => now(),
            ]);

            return 0;
        }

        $created = $this->store($request, $candidates);

        $request->update([
            'status' => RequestStatus::Completed,
            'candidates_found' => $created,
            'error' => $errors === [] ? null : implode("\n", $errors),
            'completed_at' => now(),
        ]);

        return $created;
    }

    /**
     * Search, classify against the request, then extract the best hits. The
     * classify and extract steps are the scheduled pipeline's own, unchanged;
     * only the scoring rubric and the row filter differ.
     *
     * @return array<int, array<string, mixed>>
     */
    private function youtubeLane(RecipeRequest $request): array
    {
        $this->search->handle($request, (int) config('mealboard.request_search_results'));
        $this->classify->handle($request);

        $survivors = DiscoveredVideo::query()
            ->where('recipe_request_id', $request->id)
            ->where('classification', VideoClassification::LikelyRecipe)
            ->whereNull('processed_at')
            ->whereNull('error')
            ->orderByDesc('score')
            ->limit((int) config('mealboard.request_candidates_per_lane'))
            ->get();

        $candidates = [];

        foreach ($survivors as $video) {
            $candidate = $this->extract->handle($video);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function claudeLane(RecipeRequest $request): array
    {
        return $this->anthropic->discoverFor($request, (int) config('mealboard.request_candidates_per_lane'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function store(RecipeRequest $request, array $candidates): int
    {
        $knownTitles = $this->duplicates->knownTitles();
        $created = 0;

        foreach ($candidates as $candidate) {
            if ($this->duplicates->isDuplicate($candidate['title'], $knownTitles)) {
                continue;
            }

            $ingredients = $candidate['ingredients'];
            unset($candidate['ingredients']);

            $this->createRecipe->handle([
                ...$candidate,
                'source' => RecipeSource::Discovered,
                'recipe_request_id' => $request->id,
                'discovered_at' => now(),
            ], $ingredients);

            $knownTitles[] = mb_strtolower(trim($candidate['title']));
            $created++;
        }

        return $created;
    }
}
