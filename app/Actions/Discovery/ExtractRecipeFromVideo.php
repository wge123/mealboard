<?php

namespace App\Actions\Discovery;

use App\Discovery\CandidateValidator;
use App\Discovery\ClaudeCli;
use App\Models\DiscoveredVideo;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Turn one likely_recipe video into a validated recipe candidate:
 * transcript via a thin python subprocess (DECISIONS.md #4 — yt2md's venv,
 * youtube_transcript_api, transcript ONLY), then structuring via the shared
 * claude CLI helper.
 *
 * Failures (captionless video, empty transcript, malformed recipe JSON) are
 * loud but per-video: the error lands on the discovered_videos row and null
 * is returned so the caller continues with the other videos.
 */
class ExtractRecipeFromVideo
{
    /**
     * Thin transcript-only script; the video id is passed as argv, never
     * interpolated into the code.
     */
    private const TRANSCRIPT_SCRIPT = <<<'PYTHON'
    import sys
    from youtube_transcript_api import YouTubeTranscriptApi
    print(" ".join(s.text for s in YouTubeTranscriptApi().fetch(sys.argv[1])))
    PYTHON;

    public function __construct(
        private ClaudeCli $claude,
        private CandidateValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>|null candidate, or null when the failure
     *                                   was recorded on the video row
     */
    public function handle(DiscoveredVideo $video): ?array
    {
        try {
            $transcript = $this->transcript($video->video_id);
            $candidate = $this->extract($video, $transcript);
        } catch (RuntimeException $e) {
            $video->update(['error' => $e->getMessage()]);

            return null;
        }

        $video->update(['processed_at' => now(), 'error' => null]);

        return $candidate;
    }

    private function transcript(string $videoId): string
    {
        $result = Process::timeout(120)->run([
            (string) config('mealboard.yt_python'),
            '-c',
            self::TRANSCRIPT_SCRIPT,
            $videoId,
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'transcript fetch failed (exit '.$result->exitCode().'): '
                .trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
            );
        }

        $transcript = trim($result->output());

        if ($transcript === '') {
            throw new RuntimeException("transcript was empty for video {$videoId}");
        }

        return $transcript;
    }

    /**
     * @return array<string, mixed>
     */
    private function extract(DiscoveredVideo $video, string $transcript): array
    {
        $output = $this->claude->run($this->prompt($video, $transcript));

        $item = json_decode($this->claude->extractJson($output), true);

        if (is_array($item)) {
            // The video IS the source — inject it before schema validation so
            // the validator's source_url requirement holds on this path too.
            $item['source_url'] = "https://www.youtube.com/watch?v={$video->video_id}";
        }

        $candidate = $this->validator->validate($item);

        if ($candidate === null) {
            throw new RuntimeException(
                'claude recipe output failed schema validation: '.mb_substr(trim($output), 0, 300),
            );
        }

        return $candidate;
    }

    private function prompt(DiscoveredVideo $video, string $transcript): string
    {
        return <<<PROMPT
        You are the recipe extraction engine for a household meal planner. Below is the transcript of a YouTube cooking video. Extract ONE cookable recipe from it.

        Video title: {$video->title}
        Video description: {$video->description}

        Transcript:
        {$transcript}

        Respond with STRICT JSON only: a single top-level object. No prose, no markdown fences, no trailing commentary. The object has exactly these keys:
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
}
