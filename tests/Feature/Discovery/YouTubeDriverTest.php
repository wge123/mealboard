<?php

use App\Discovery\AnthropicDriver;
use App\Discovery\TheMealDbDriver;
use App\Discovery\YouTubeDriver;
use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;
use App\Models\YouTubeChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function driverRecipeObject(string $title): array
{
    return [
        'title' => $title,
        'description' => 'A quick extracted recipe.',
        'meal_type' => 'dinner',
        'prep_minutes' => 5,
        'cook_minutes' => 15,
        'servings' => 2,
        'instructions' => "1. Cook.\n2. Eat.",
        'cuisine' => null,
        'tags' => ['quick'],
        'source_url' => null,
        'ingredients' => [
            ['qty' => 1, 'unit' => null, 'name' => 'main thing', 'note' => null],
        ],
    ];
}

function driverFeedXml(string $channelId, array $videos): string
{
    $entries = '';

    foreach ($videos as $id => $title) {
        $entries .= <<<XML
          <entry>
            <yt:videoId>{$id}</yt:videoId>
            <title>{$title}</title>
            <published>2026-07-17T09:00:00+00:00</published>
            <media:group><media:description>{$title} description</media:description></media:group>
          </entry>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <feed xmlns:yt="http://www.youtube.com/xml/schemas/2015"
          xmlns:media="http://search.yahoo.com/mrss/"
          xmlns="http://www.w3.org/2005/Atom">{$entries}</feed>
    XML;
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    config()->set('mealboard.yt_python', '/fake/bin/python3');
});

it('is registered between the anthropic and themealdb drivers', function () {
    expect(config('mealboard.drivers'))->toBe([
        AnthropicDriver::class,
        YouTubeDriver::class,
        TheMealDbDriver::class,
    ]);
});

it('chains poll, classify, and extract into recipe candidates', function () {
    YouTubeChannel::factory()->create(['channel_id' => 'UCchain']);

    Http::fake([
        'https://www.youtube.com/feeds/videos.xml*' => Http::response(driverFeedXml('UCchain', [
            'vidRecipe01' => 'Weeknight Chickpea Curry',
            'vidTour0001' => 'My New Kitchen Tour',
        ])),
    ]);

    Process::fake(function ($process) {
        if ($process->command[0] === '/fake/bin/python3') {
            return Process::result(output: 'simmer the chickpeas in coconut milk');
        }

        if (str_contains($process->command[2], 'pre-filter')) {
            return Process::result(output: json_encode([
                ['video_id' => 'vidRecipe01', 'classification' => 'likely_recipe', 'score' => 90],
                ['video_id' => 'vidTour0001', 'classification' => 'not_recipe', 'score' => 5],
            ]));
        }

        return Process::result(output: json_encode(driverRecipeObject('Weeknight Chickpea Curry')));
    });

    $candidates = app(YouTubeDriver::class)->discover(3);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['title'])->toBe('Weeknight Chickpea Curry')
        ->and($candidates[0]['source_url'])->toBe('https://www.youtube.com/watch?v=vidRecipe01');

    $tour = DiscoveredVideo::firstWhere('video_id', 'vidTour0001');

    expect($tour->classification)->toBe(VideoClassification::NotRecipe)
        ->and($tour->processed_at)->toBeNull();
});

it('extracts at most n survivors, highest score first', function () {
    DiscoveredVideo::factory()->likelyRecipe(95)->create(['video_id' => 'vidTop00001']);
    DiscoveredVideo::factory()->likelyRecipe(80)->create(['video_id' => 'vidMid00001']);
    DiscoveredVideo::factory()->likelyRecipe(60)->create(['video_id' => 'vidLow00001']);

    Http::fake();
    Process::fake(function ($process) {
        if ($process->command[0] === '/fake/bin/python3') {
            return Process::result(output: 'a transcript');
        }

        return Process::result(output: json_encode(driverRecipeObject('Extracted Recipe')));
    });

    expect(app(YouTubeDriver::class)->discover(2))->toHaveCount(2);

    expect(DiscoveredVideo::firstWhere('video_id', 'vidTop00001')->processed_at)->not->toBeNull()
        ->and(DiscoveredVideo::firstWhere('video_id', 'vidMid00001')->processed_at)->not->toBeNull()
        ->and(DiscoveredVideo::firstWhere('video_id', 'vidLow00001')->processed_at)->toBeNull();
});

it('continues with remaining videos after a captionless failure', function () {
    DiscoveredVideo::factory()->likelyRecipe(90)->create(['video_id' => 'vidBroken01']);
    DiscoveredVideo::factory()->likelyRecipe(70)->create(['video_id' => 'vidWorks001']);

    Http::fake();
    Process::fake(function ($process) {
        if ($process->command[0] === '/fake/bin/python3') {
            return $process->command[3] === 'vidBroken01'
                ? Process::result(output: '', errorOutput: 'no captions', exitCode: 1)
                : Process::result(output: 'a transcript');
        }

        return Process::result(output: json_encode(driverRecipeObject('Surviving Recipe')));
    });

    $candidates = app(YouTubeDriver::class)->discover(3);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['source_url'])->toBe('https://www.youtube.com/watch?v=vidWorks001');

    expect(DiscoveredVideo::firstWhere('video_id', 'vidBroken01')->error)->toContain('transcript fetch failed');
});

it('skips previously errored and processed videos on later runs', function () {
    DiscoveredVideo::factory()->likelyRecipe()->create(['error' => 'transcript fetch failed: no captions']);
    DiscoveredVideo::factory()->likelyRecipe()->processed()->create();
    DiscoveredVideo::factory()->notRecipe()->create();

    Http::fake();
    Process::fake();

    expect(app(YouTubeDriver::class)->discover(3))->toBe([]);

    Process::assertDidntRun(fn ($process) => $process->command[0] === '/fake/bin/python3');
});
