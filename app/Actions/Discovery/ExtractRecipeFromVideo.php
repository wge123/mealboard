<?php

namespace App\Actions\Discovery;

use App\Discovery\CandidateCheck;
use App\Discovery\CandidateValidator;
use App\Discovery\ClaudeCli;
use App\Discovery\RecipeOutputFormat;
use App\Discovery\RetryOnce;
use App\Exceptions\DiscoveryEnvironmentException;
use App\Models\DiscoveredVideo;
use App\Support\KitchenToolInventory;
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
 * is returned so the caller continues with the other videos. Recording the
 * error is permanent, since YouTubeDriver filters on whereNull('error'), which
 * is why a broken environment must NOT take that path; see
 * DiscoveryEnvironmentException.
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
        private RetryOnce $retry,
        private KitchenToolInventory $tools,
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
        $python = (string) config('mealboard.yt_python');

        $result = Process::timeout(120)->run([
            $python,
            '-c',
            self::TRANSCRIPT_SCRIPT,
            $videoId,
        ]);

        if ($result->failed()) {
            // Diagnose the shell's "cannot execute" codes before blaming the
            // video. yt_python is an absolute path into another project's venv,
            // so it breaks whenever that project is moved or rebuilt, and the
            // resulting 126/127 is otherwise indistinguishable from a
            // captionless video. Taking the per-video path there would record
            // an error on the row and blacklist a perfectly good video forever
            // (YouTubeDriver filters on whereNull('error')), one per run, for a
            // fault that has nothing to do with it.
            if (in_array($result->exitCode(), [126, 127], true)) {
                throw new DiscoveryEnvironmentException(
                    "yt_python at '{$python}' could not be executed (exit ".$result->exitCode().'): '
                    .trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
                );
            }

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
        $checked = $this->retry->run(
            $this->prompt($video, $transcript),
            fn (string $output) => $this->check($video, $output)->errors,
        );

        $candidate = $this->check($video, $checked->output)->candidate;

        if ($candidate === null) {
            throw new RuntimeException(
                'claude recipe output failed schema validation: '.implode('; ', $checked->errors),
            );
        }

        return $candidate;
    }

    private function check(DiscoveredVideo $video, string $output): CandidateCheck
    {
        $item = json_decode($this->claude->extractJson($output), true);

        if (is_array($item)) {
            // The video IS the source — inject it before the check so the
            // source_url requirement holds on this path too.
            $item['source_url'] = "https://www.youtube.com/watch?v={$video->video_id}";
        }

        return $this->validator->check($item);
    }

    private function prompt(DiscoveredVideo $video, string $transcript): string
    {
        $kinds = RecipeOutputFormat::kindsSection($this->tools->kinds());
        $schema = RecipeOutputFormat::schema('null');

        return <<<PROMPT
        You are the recipe extraction engine for a household meal planner. Below is the transcript of a YouTube cooking video. Extract ONE cookable recipe from it.

        Video title: {$video->title}
        Video description: {$video->description}

        Transcript:
        {$transcript}

        {$kinds}

        Respond with STRICT JSON only: a single top-level object. No prose, no markdown fences, no trailing commentary. The object has exactly these keys:
        {$schema}
        PROMPT;
    }
}
