<?php

namespace App\Actions\Discovery;

use App\Models\DiscoveredVideo;
use App\Models\RecipeRequest;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Keyword search half of the requester: turn one request's text into
 * discovered_videos rows via yt-dlp's `ytsearch` pseudo-extractor.
 *
 * yt-dlp rather than the YouTube Data API (DECISIONS-style call, recorded in
 * .plans/light-plan-recipe-requester.md): it is already installed, needs no
 * API key, and has no quota to exhaust on a household that searches a few
 * times a week.
 *
 * The rows this writes are ordinary discovered_videos, so the existing
 * classify and extract chain processes them unchanged.
 */
class SearchYouTube
{
    /**
     * Flat listing: ONE network round trip for the whole result page instead
     * of a metadata extraction per video. The trade is that description and
     * publication date come back null, which is why both columns are nullable.
     */
    private const array FLAGS = [
        '--flat-playlist',
        '--dump-json',
        '--no-warnings',
        '--ignore-config',
    ];

    /**
     * @return int number of newly inserted discovered_videos rows
     */
    public function handle(RecipeRequest $request, int $n): int
    {
        $result = Process::timeout(180)->run([
            $this->binary(),
            ...self::FLAGS,
            "ytsearch{$n}:{$request->query}",
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'yt-dlp search failed (exit '.$result->exitCode().'): '
                .trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
            );
        }

        return $this->store($request, $this->parse($result->output()));
    }

    /**
     * yt-dlp emits one JSON object per line. A line that will not decode is
     * skipped rather than failing the search: the remaining hits are still
     * usable, and a malformed line is yt-dlp's problem, not the household's.
     *
     * @return array<int, array{video_id: string, title: string, channel_id: string}>
     */
    private function parse(string $output): array
    {
        $entries = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (! is_array($decoded)) {
                continue;
            }

            $videoId = trim((string) ($decoded['id'] ?? ''));
            $title = trim((string) ($decoded['title'] ?? ''));

            if ($videoId === '' || $title === '') {
                continue;
            }

            $entries[] = [
                'video_id' => $videoId,
                'title' => $title,
                // A search hit still names its channel; it is simply not a
                // channel the household subscribed to.
                'channel_id' => trim((string) ($decoded['channel_id'] ?? '')),
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{video_id: string, title: string, channel_id: string}>  $entries
     */
    private function store(RecipeRequest $request, array $entries): int
    {
        $inserted = 0;

        foreach ($entries as $entry) {
            // firstOrCreate, not create: a video the channel poller already
            // saw keeps its existing row, its classification, and above all
            // its processed_at, so a request never re-extracts a video that is
            // already a recipe in the library.
            $video = DiscoveredVideo::query()->firstOrCreate(
                ['video_id' => $entry['video_id']],
                [
                    'channel_id' => $entry['channel_id'],
                    'recipe_request_id' => $request->id,
                    'title' => $entry['title'],
                    'description' => null,
                    'published_at' => null,
                ],
            );

            if ($video->wasRecentlyCreated) {
                $inserted++;
            }
        }

        return $inserted;
    }

    private function binary(): string
    {
        $configured = config('mealboard.yt_dlp');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $found = (new ExecutableFinder)->find('yt-dlp');

        if ($found === null) {
            throw new RuntimeException(
                'yt-dlp not found on PATH; install it or set mealboard.yt_dlp.',
            );
        }

        return $found;
    }
}
