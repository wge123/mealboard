<?php

namespace App\Discovery;

use App\Actions\Discovery\ClassifyDiscoveredVideos;
use App\Actions\Discovery\ExtractRecipeFromVideo;
use App\Actions\Discovery\PollYouTubeChannels;
use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;

/**
 * YouTube pipeline driver: poll active channels' RSS feeds (step 16),
 * batch-classify new videos (step 17), then extract recipes from up to $n
 * of the highest-scoring likely_recipe survivors (step 18).
 *
 * Candidate source_url is https://www.youtube.com/watch?v={video_id}; a
 * thumbnail needs no column because it is derivable by convention:
 * https://i.ytimg.com/vi/{video_id}/hqdefault.jpg
 */
class YouTubeDriver implements RecipeDiscoveryDriver
{
    public function __construct(
        private PollYouTubeChannels $poll,
        private ClassifyDiscoveredVideos $classify,
        private ExtractRecipeFromVideo $extract,
    ) {}

    public function discover(int $n): array
    {
        $this->poll->handle();
        $this->classify->handle();

        // Errored videos (captionless etc.) are excluded so a permanently
        // broken video is never retried run after run.
        $survivors = DiscoveredVideo::query()
            ->where('classification', VideoClassification::LikelyRecipe)
            ->whereNull('processed_at')
            ->whereNull('error')
            ->orderByDesc('score')
            ->limit($n)
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
}
