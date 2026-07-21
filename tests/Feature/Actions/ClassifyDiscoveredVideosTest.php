<?php

use App\Actions\Discovery\ClassifyDiscoveredVideos;
use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;
use App\Models\Recipe;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
});

it('classifies every unclassified video in one batch claude call', function () {
    $recipe = DiscoveredVideo::factory()->create(['video_id' => 'vidRecipe01', 'title' => '15-Minute Garlic Noodles']);
    $tour = DiscoveredVideo::factory()->create(['video_id' => 'vidTour0001', 'title' => 'My Kitchen Tour']);

    Process::fake(['*' => Process::result(output: json_encode([
        ['video_id' => 'vidRecipe01', 'classification' => 'likely_recipe', 'score' => 85],
        ['video_id' => 'vidTour0001', 'classification' => 'not_recipe', 'score' => 5],
    ]))]);

    expect(app(ClassifyDiscoveredVideos::class)->handle())->toBe(2);

    Process::assertRanTimes(fn ($process) => $process->command[0] === '/fake/bin/claude', 1);

    expect($recipe->refresh()->classification)->toBe(VideoClassification::LikelyRecipe)
        ->and($recipe->score)->toBe(85)
        ->and($tour->refresh()->classification)->toBe(VideoClassification::NotRecipe)
        ->and($tour->score)->toBe(5);
});

it('sends titles and descriptions in the batch prompt', function () {
    DiscoveredVideo::factory()->create([
        'video_id' => 'vidPrompt01',
        'title' => 'Sheet-Pan Gnocchi',
        'description' => 'One pan, 20 minutes.',
    ]);

    Process::fake(['*' => Process::result(output: json_encode([
        ['video_id' => 'vidPrompt01', 'classification' => 'likely_recipe', 'score' => 90],
    ]))]);

    app(ClassifyDiscoveredVideos::class)->handle();

    Process::assertRan(function ($process) {
        $prompt = $process->command[2];

        return $process->command[1] === '-p'
            && $process->command[3] === '--model'
            && $process->command[4] === 'sonnet'
            && str_contains($prompt, 'vidPrompt01')
            && str_contains($prompt, 'Sheet-Pan Gnocchi')
            && str_contains($prompt, 'One pan, 20 minutes.')
            && str_contains($prompt, '30 minutes or less')
            && str_contains($prompt, '10 ingredients or fewer')
            && str_contains($prompt, "Classification history from past runs:\n(none yet)");
    });
});

it('skips already-classified videos and makes no call when none are unclassified', function () {
    DiscoveredVideo::factory()->likelyRecipe()->create();
    DiscoveredVideo::factory()->notRecipe()->create();

    Process::fake();

    expect(app(ClassifyDiscoveredVideos::class)->handle())->toBe(0);

    Process::assertNothingRan();
});

it('fails loudly and persists nothing when the response is not a JSON array', function () {
    $video = DiscoveredVideo::factory()->create();

    Process::fake(['*' => Process::result(output: 'I cannot classify these videos.')]);

    expect(fn () => app(ClassifyDiscoveredVideos::class)->handle())
        ->toThrow(RuntimeException::class, 'not a JSON array');

    expect($video->refresh()->classification)->toBeNull()
        ->and($video->score)->toBeNull();
});

it('fails loudly and persists nothing when any row is malformed', function () {
    $first = DiscoveredVideo::factory()->create(['video_id' => 'vidGood0001']);
    $second = DiscoveredVideo::factory()->create(['video_id' => 'vidBad00001']);

    Process::fake(['*' => Process::result(output: json_encode([
        ['video_id' => 'vidGood0001', 'classification' => 'likely_recipe', 'score' => 80],
        ['video_id' => 'vidBad00001', 'classification' => 'maybe_recipe', 'score' => 50],
    ]))]);

    expect(fn () => app(ClassifyDiscoveredVideos::class)->handle())
        ->toThrow(RuntimeException::class, 'malformed');

    expect($first->refresh()->classification)->toBeNull()
        ->and($second->refresh()->classification)->toBeNull();
});

it('ignores unknown video ids and leaves unmentioned videos unclassified', function () {
    $mentioned = DiscoveredVideo::factory()->create(['video_id' => 'vidKnown001']);
    $unmentioned = DiscoveredVideo::factory()->create(['video_id' => 'vidLeft0001']);

    Process::fake(['*' => Process::result(output: json_encode([
        ['video_id' => 'vidKnown001', 'classification' => 'likely_recipe', 'score' => 75],
        ['video_id' => 'vidNeverSent', 'classification' => 'not_recipe', 'score' => 1],
    ]))]);

    expect(app(ClassifyDiscoveredVideos::class)->handle())->toBe(1);

    expect($mentioned->refresh()->classification)->toBe(VideoClassification::LikelyRecipe)
        ->and($unmentioned->refresh()->classification)->toBeNull();
});

it('includes youtube approve/reject history in the classifier prompt', function () {
    Recipe::factory()->approved()->create([
        'title' => 'Approved Garlic Noodles',
        'source_url' => 'https://www.youtube.com/watch?v=abc12345678',
    ]);
    Recipe::factory()->create([
        'status' => 'rejected',
        'title' => 'Rejected Fish Stew',
        'source_url' => 'https://youtu.be/def12345678',
    ]);
    Recipe::factory()->approved()->create(['title' => 'Manual Pasta', 'source_url' => 'https://example.com/recipes/manual-pasta']);
    Recipe::factory()->create([
        'title' => 'Pending YouTube Soup',
        'source_url' => 'https://www.youtube.com/watch?v=ghi12345678',
    ]);

    DiscoveredVideo::factory()->create(['video_id' => 'vidHist0001']);

    Process::fake(['*' => Process::result(output: json_encode([
        ['video_id' => 'vidHist0001', 'classification' => 'likely_recipe', 'score' => 80],
    ]))]);

    app(ClassifyDiscoveredVideos::class)->handle();

    Process::assertRan(function ($process) {
        $prompt = $process->command[2];

        return str_contains($prompt, 'APPROVED: Approved Garlic Noodles')
            && str_contains($prompt, 'REJECTED: Rejected Fish Stew')
            // Non-YouTube and still-pending recipes stay out of the history.
            && ! str_contains($prompt, 'Manual Pasta')
            && ! str_contains($prompt, 'Pending YouTube Soup')
            && ! str_contains($prompt, "Classification history from past runs:\n(none yet)");
    });
});
