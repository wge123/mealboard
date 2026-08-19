<?php

use App\Actions\Discovery\SearchYouTube;
use App\Models\DiscoveredVideo;
use App\Models\RecipeRequest;
use Illuminate\Support\Facades\Process;

function ytSearchLine(string $id, string $title, ?string $channelId = 'UCchannel001'): string
{
    return json_encode([
        '_type' => 'url',
        'id' => $id,
        'title' => $title,
        'channel_id' => $channelId,
        // yt-dlp's flat listing genuinely returns null for both of these.
        'description' => null,
        'timestamp' => null,
    ]);
}

beforeEach(function () {
    config()->set('mealboard.yt_dlp', '/fake/bin/yt-dlp');
});

it('stores one discovered video per search hit, tagged with the request', function () {
    $request = RecipeRequest::factory()->create(['query' => 'hibachi blackstone']);

    Process::fake(['*yt-dlp*' => Process::result(output: implode("\n", [
        ytSearchLine('vidHibachi1', 'Hibachi at Home on the Blackstone Griddle'),
        ytSearchLine('vidHibachi2', 'Blackstone Hibachi Fried Rice'),
    ]))]);

    $inserted = app(SearchYouTube::class)->handle($request, 2);

    expect($inserted)->toBe(2)
        ->and(DiscoveredVideo::where('recipe_request_id', $request->id)->count())->toBe(2);

    $video = DiscoveredVideo::where('video_id', 'vidHibachi1')->sole();

    expect($video->title)->toBe('Hibachi at Home on the Blackstone Griddle')
        ->and($video->channel_id)->toBe('UCchannel001')
        // Null rather than invented: the flat listing does not carry either.
        ->and($video->description)->toBeNull()
        ->and($video->published_at)->toBeNull()
        ->and($video->classification)->toBeNull();
});

it('passes the request text to yt-dlp as an ytsearch term', function () {
    $request = RecipeRequest::factory()->create(['query' => 'hibachi for 4']);

    Process::fake(['*yt-dlp*' => Process::result(output: ytSearchLine('vidOnly1', 'Hibachi'))]);

    app(SearchYouTube::class)->handle($request, 7);

    Process::assertRan(fn ($process) => in_array('ytsearch7:hibachi for 4', $process->command, true));
});

it('leaves an already-discovered video untouched so a request never re-extracts it', function () {
    $request = RecipeRequest::factory()->create();

    $existing = DiscoveredVideo::factory()->create([
        'video_id' => 'vidSeenBefore',
        'title' => 'Seen by the channel poller',
        'processed_at' => now()->subDay(),
    ]);

    Process::fake(['*yt-dlp*' => Process::result(output: implode("\n", [
        ytSearchLine('vidSeenBefore', 'Different title from search'),
        ytSearchLine('vidBrandNew', 'Brand new hit'),
    ]))]);

    $inserted = app(SearchYouTube::class)->handle($request, 2);

    expect($inserted)->toBe(1)
        ->and($existing->fresh()->title)->toBe('Seen by the channel poller')
        ->and($existing->fresh()->recipe_request_id)->toBeNull()
        ->and($existing->fresh()->processed_at)->not->toBeNull();
});

it('skips lines that are not decodable JSON rather than failing the search', function () {
    $request = RecipeRequest::factory()->create();

    Process::fake(['*yt-dlp*' => Process::result(output: implode("\n", [
        'WARNING: your yt-dlp is out of date',
        ytSearchLine('vidGood1', 'A real hit'),
        '',
    ]))]);

    expect(app(SearchYouTube::class)->handle($request, 5))->toBe(1);
});

it('fails loudly when yt-dlp exits non-zero', function () {
    $request = RecipeRequest::factory()->create();

    Process::fake(['*yt-dlp*' => Process::result(errorOutput: 'ERROR: unable to extract', exitCode: 1)]);

    expect(fn () => app(SearchYouTube::class)->handle($request, 5))
        ->toThrow(RuntimeException::class, 'yt-dlp search failed');
});
