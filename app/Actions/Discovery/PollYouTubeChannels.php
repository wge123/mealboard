<?php

namespace App\Actions\Discovery;

use App\Models\DiscoveredVideo;
use App\Models\YouTubeChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PollYouTubeChannels
{
    /**
     * YouTube's public per-channel RSS feed — no OAuth, no API key.
     */
    private const FEED_URL = 'https://www.youtube.com/feeds/videos.xml';

    private const NS_YT = 'http://www.youtube.com/xml/schemas/2015';

    private const NS_MEDIA = 'http://search.yahoo.com/mrss/';

    /**
     * Poll every ACTIVE channel's RSS feed and record unseen videos.
     *
     * @return int number of newly inserted discovered_videos rows
     */
    public function handle(): int
    {
        $inserted = 0;

        foreach (YouTubeChannel::query()->where('active', true)->get() as $channel) {
            $xml = Http::get(self::FEED_URL, ['channel_id' => $channel->channel_id])
                ->throw()
                ->body();

            $inserted += $this->storeUnseen($channel->channel_id, $this->parseFeed($xml));
        }

        return $inserted;
    }

    /**
     * @return array<int, array{video_id: string, title: string, description: string, published_at: string}>
     */
    private function parseFeed(string $xml): array
    {
        $feed = @simplexml_load_string($xml);

        if ($feed === false) {
            throw new RuntimeException('YouTube RSS feed was not parseable XML.');
        }

        $entries = [];

        foreach ($feed->entry as $entry) {
            $videoId = trim((string) $entry->children(self::NS_YT)->videoId);

            if ($videoId === '') {
                continue;
            }

            $entries[] = [
                'video_id' => $videoId,
                'title' => trim((string) $entry->title),
                'description' => trim((string) $entry->children(self::NS_MEDIA)->group->description),
                'published_at' => (string) $entry->published,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{video_id: string, title: string, description: string, published_at: string}>  $entries
     */
    private function storeUnseen(string $channelId, array $entries): int
    {
        $seen = DiscoveredVideo::query()
            ->whereIn('video_id', array_column($entries, 'video_id'))
            ->pluck('video_id')
            ->all();

        $inserted = 0;

        foreach ($entries as $entry) {
            if (in_array($entry['video_id'], $seen, true)) {
                continue;
            }

            DiscoveredVideo::create([...$entry, 'channel_id' => $channelId]);
            $inserted++;
        }

        return $inserted;
    }
}
