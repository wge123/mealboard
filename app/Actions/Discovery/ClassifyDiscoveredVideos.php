<?php

namespace App\Actions\Discovery;

use App\Discovery\ClaudeCli;
use App\Enums\RecipeStatus;
use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;
use App\Models\Recipe;
use App\Models\RecipeRequest;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Pre-filter: batch-classify every unclassified discovered video in ONE
 * claude CLI call. Only likely_recipe survivors proceed to transcript
 * extraction (step 18).
 */
class ClassifyDiscoveredVideos
{
    /** Most recent verdicts included in the prompt (newest first). */
    private const int HISTORY_CAP = 20;

    public function __construct(
        private ClaudeCli $claude,
    ) {}

    /**
     * Classify one batch of unclassified videos.
     *
     * The two lanes are scored on DIFFERENT rubrics and must never be mixed.
     * Scheduled discovery judges a video against the household's weeknight
     * criteria (fast, few ingredients); a request judges it against what the
     * household actually asked for. Scoring request videos on the weeknight
     * rubric is not a cosmetic mismatch: YouTubeDriver and RunRequest both take
     * survivors orderByDesc('score'), so a correctly found hibachi video would
     * be marked down for being a 45-minute cook and lose to whatever generic
     * quick dinner happened to be in the same batch.
     *
     * @param  RecipeRequest|null  $request  null classifies the scheduled lane
     * @return int number of videos classified
     */
    public function handle(?RecipeRequest $request = null): int
    {
        $videos = DiscoveredVideo::query()
            ->whereNull('classification')
            ->when(
                $request !== null,
                fn ($query) => $query->where('recipe_request_id', $request->id),
                fn ($query) => $query->whereNull('recipe_request_id'),
            )
            ->get();

        if ($videos->isEmpty()) {
            return 0;
        }

        $output = $this->claude->run($this->prompt($videos, $request));

        $rows = $this->parseClassifications($output);

        $classified = 0;

        foreach ($rows as $row) {
            $video = $videos->firstWhere('video_id', $row['video_id']);

            if ($video === null) {
                continue; // A video_id we never sent — ignore it.
            }

            $video->update([
                'classification' => $row['classification'],
                'score' => $row['score'],
            ]);
            $classified++;
        }

        return $classified;
    }

    /**
     * @param  Collection<int, DiscoveredVideo>  $videos
     */
    private function prompt(Collection $videos, ?RecipeRequest $request = null): string
    {
        $history = $this->classifierHistory();
        $historySection = $history !== '' ? $history : '(none yet)';

        $payload = json_encode(
            $videos->map(fn (DiscoveredVideo $video) => [
                'video_id' => $video->video_id,
                'title' => $video->title,
                'description' => $video->description,
            ])->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $criteria = $request !== null
            ? <<<CRITERIA
            The household has asked for this specifically:
            "{$request->query}"

            Score 0-100 purely on how well the video delivers THAT request. The household's usual weeknight limits (30 minutes, 10 ingredients) DO NOT apply here: they asked for this dish by name, so a long cook or a long ingredient list is not a mark against it. A video that is a fine recipe but not what was asked for scores low.
            CRITERIA
            : <<<'CRITERIA'
            Score 0-100 how well it fits the household's criteria (healthy + easy):
            - Total time (prep + cook) 30 minutes or less.
            - 10 ingredients or fewer.
            - Whole-food-leaning: minimally processed ingredients over packaged or ultra-processed ones.
            CRITERIA;

        return <<<PROMPT
        You are the pre-filter for a household meal planner's YouTube discovery pipeline. For each video below, judge from its title and description whether it likely contains a cookable recipe, then score it.

        {$criteria}

        Videos that are kitchen tours, gear reviews, vlogs, restaurant visits, or multi-hour projects are not_recipe.

        Some videos come from a keyword search rather than a subscribed channel, so their description is null. Judge those on the title alone.

        Classification history from past runs:
        {$historySection}

        Videos (JSON):
        {$payload}

        Respond with STRICT JSON only: a top-level array with exactly one object per video. No prose, no markdown fences, no trailing commentary. Each object has exactly these keys:
        {"video_id": string, "classification": "likely_recipe" | "not_recipe", "score": integer 0-100}
        PROMPT;
    }

    /**
     * Approve/reject history of YouTube-sourced recipes (titles + verdicts,
     * newest first, capped) so video scoring learns from past outcomes.
     * Empty string on a fresh install keeps the "(none yet)" placeholder.
     */
    protected function classifierHistory(): string
    {
        return Recipe::query()
            ->whereIn('status', [RecipeStatus::Approved, RecipeStatus::Rejected])
            ->where(fn ($query) => $query
                ->where('source_url', 'like', '%youtube.com%')
                ->orWhere('source_url', 'like', '%youtu.be%'))
            ->orderByDesc('id')
            ->limit(self::HISTORY_CAP)
            ->get()
            ->map(fn (Recipe $recipe) => strtoupper($recipe->status->value).': '.$recipe->title)
            ->implode("\n");
    }

    /**
     * Strict validation: a malformed response overall (not an array, or any
     * malformed row) fails the run loudly and classifies NOTHING.
     *
     * @return array<int, array{video_id: string, classification: VideoClassification, score: int}>
     */
    private function parseClassifications(string $output): array
    {
        $decoded = json_decode($this->claude->extractJson($output), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException(
                'claude classification output was not a JSON array: '.mb_substr(trim($output), 0, 300),
            );
        }

        $rows = [];

        foreach ($decoded as $item) {
            $classification = is_array($item) && is_string($item['classification'] ?? null)
                ? VideoClassification::tryFrom($item['classification'])
                : null;

            if (! is_array($item)
                || ! is_string($item['video_id'] ?? null)
                || $item['video_id'] === ''
                || $classification === null
                || ! is_int($item['score'] ?? null)
                || $item['score'] < 0
                || $item['score'] > 100) {
                throw new RuntimeException(
                    'claude classification row was malformed: '.json_encode($item),
                );
            }

            $rows[] = [
                'video_id' => $item['video_id'],
                'classification' => $classification,
                'score' => $item['score'],
            ];
        }

        return $rows;
    }
}
