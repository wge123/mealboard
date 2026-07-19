<?php

namespace App\Actions\Discovery;

use App\Discovery\ClaudeCli;
use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Pre-filter: batch-classify every unclassified discovered video in ONE
 * claude CLI call. Only likely_recipe survivors proceed to transcript
 * extraction (step 18).
 */
class ClassifyDiscoveredVideos
{
    public function __construct(
        private ClaudeCli $claude,
    ) {}

    /**
     * @return int number of videos classified
     */
    public function handle(): int
    {
        $videos = DiscoveredVideo::query()->whereNull('classification')->get();

        if ($videos->isEmpty()) {
            return 0;
        }

        $output = $this->claude->run($this->prompt($videos));

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
    private function prompt(Collection $videos): string
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

        return <<<PROMPT
        You are the pre-filter for a household meal planner's YouTube discovery pipeline. For each video below, judge from its title and description whether it likely contains a cookable recipe, and score 0-100 how well it fits the household's criteria (healthy + easy):
        - Total time (prep + cook) 30 minutes or less.
        - 10 ingredients or fewer.
        - Whole-food-leaning: minimally processed ingredients over packaged or ultra-processed ones.

        Videos that are kitchen tours, gear reviews, vlogs, restaurant visits, or multi-hour projects are not_recipe.

        Classification history from past runs:
        {$historySection}

        Videos (JSON):
        {$payload}

        Respond with STRICT JSON only: a top-level array with exactly one object per video. No prose, no markdown fences, no trailing commentary. Each object has exactly these keys:
        {"video_id": string, "classification": "likely_recipe" | "not_recipe", "score": integer 0-100}
        PROMPT;
    }

    /**
     * Classifier-history prompt section — wired to real feedback data in
     * step 24 (mirrors AnthropicDriver's taste-profile stub).
     */
    protected function classifierHistory(): string
    {
        return '';
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
