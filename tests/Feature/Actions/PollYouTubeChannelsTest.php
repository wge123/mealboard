<?php

use App\Actions\Discovery\PollYouTubeChannels;
use App\Models\DiscoveredVideo;
use App\Models\YouTubeChannel;
use Illuminate\Support\Facades\Http;

/**
 * Build a YouTube channel RSS feed fixture from entry rows.
 *
 * @param  array<int, array{id: string, title: string, description?: string, published?: string}>  $entries
 */
function youtubeFeedXml(string $channelId, array $entries): string
{
    $entryXml = '';

    foreach ($entries as $entry) {
        $description = htmlspecialchars($entry['description'] ?? '', ENT_XML1);
        $title = htmlspecialchars($entry['title'], ENT_XML1);
        $published = $entry['published'] ?? '2026-07-17T10:00:00+00:00';

        $entryXml .= <<<XML
          <entry>
            <id>yt:video:{$entry['id']}</id>
            <yt:videoId>{$entry['id']}</yt:videoId>
            <yt:channelId>{$channelId}</yt:channelId>
            <title>{$title}</title>
            <published>{$published}</published>
            <media:group>
              <media:title>{$title}</media:title>
              <media:description>{$description}</media:description>
            </media:group>
          </entry>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <feed xmlns:yt="http://www.youtube.com/xml/schemas/2015"
          xmlns:media="http://search.yahoo.com/mrss/"
          xmlns="http://www.w3.org/2005/Atom">
      <title>Feed for {$channelId}</title>
      {$entryXml}
    </feed>
    XML;
}

it('parses feed entries into discovered_videos rows', function () {
    $channel = YouTubeChannel::factory()->create(['channel_id' => 'UCabc']);

    Http::fake([
        'https://www.youtube.com/feeds/videos.xml*' => Http::response(youtubeFeedXml('UCabc', [
            ['id' => 'vid00000001', 'title' => '15-Minute Garlic Noodles', 'description' => 'Fast weeknight noodles.', 'published' => '2026-07-16T08:00:00+00:00'],
            ['id' => 'vid00000002', 'title' => 'My Kitchen Tour', 'description' => 'A look around.'],
        ])),
    ]);

    $inserted = app(PollYouTubeChannels::class)->handle();

    expect($inserted)->toBe(2)
        ->and(DiscoveredVideo::count())->toBe(2);

    $video = DiscoveredVideo::firstWhere('video_id', 'vid00000001');

    expect($video->channel_id)->toBe('UCabc')
        ->and($video->title)->toBe('15-Minute Garlic Noodles')
        ->and($video->description)->toBe('Fast weeknight noodles.')
        ->and($video->published_at->toIso8601String())->toBe('2026-07-16T08:00:00+00:00')
        ->and($video->classification)->toBeNull()
        ->and($video->processed_at)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'channel_id=UCabc'));
});

it('inserts nothing on a second poll of the same feed', function () {
    YouTubeChannel::factory()->create(['channel_id' => 'UCabc']);

    Http::fake([
        'https://www.youtube.com/feeds/videos.xml*' => Http::response(youtubeFeedXml('UCabc', [
            ['id' => 'vid00000001', 'title' => 'One-Pan Salmon'],
        ])),
    ]);

    $action = app(PollYouTubeChannels::class);

    expect($action->handle())->toBe(1)
        ->and($action->handle())->toBe(0)
        ->and(DiscoveredVideo::count())->toBe(1);
});

it('polls multiple active channels', function () {
    YouTubeChannel::factory()->create(['channel_id' => 'UCaaa']);
    YouTubeChannel::factory()->create(['channel_id' => 'UCbbb']);

    Http::fake([
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCaaa' => Http::response(
            youtubeFeedXml('UCaaa', [['id' => 'vidAAA00001', 'title' => 'Chickpea Curry']]),
        ),
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCbbb' => Http::response(
            youtubeFeedXml('UCbbb', [['id' => 'vidBBB00001', 'title' => 'Sheet-Pan Gnocchi']]),
        ),
    ]);

    expect(app(PollYouTubeChannels::class)->handle())->toBe(2)
        ->and(DiscoveredVideo::firstWhere('video_id', 'vidAAA00001')->channel_id)->toBe('UCaaa')
        ->and(DiscoveredVideo::firstWhere('video_id', 'vidBBB00001')->channel_id)->toBe('UCbbb');
});

it('skips inactive channels', function () {
    YouTubeChannel::factory()->inactive()->create(['channel_id' => 'UCoff']);

    Http::fake();

    expect(app(PollYouTubeChannels::class)->handle())->toBe(0);

    Http::assertNothingSent();
});
