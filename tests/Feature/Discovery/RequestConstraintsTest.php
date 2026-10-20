<?php

use App\Actions\Discovery\ClassifyDiscoveredVideos;
use App\Models\DiscoveredVideo;
use App\Models\RecipeRequest;
use App\Support\HouseholdPreferences;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * The whole point of a request is that the weeknight caps stop applying. The
 * video classification prompt carries those caps, so the request rubric is
 * covered here: leaving them in silently filters out the dish that was asked for.
 */
beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Http::fake(['example.com/*' => Http::response()]);
});

it('scores request videos against the request instead of the weeknight rubric', function () {
    $request = RecipeRequest::factory()->create(['query' => 'hibachi for 4']);

    $video = DiscoveredVideo::factory()->create([
        'video_id' => 'vidReq001',
        'recipe_request_id' => $request->id,
        'classification' => null,
    ]);

    Process::fake(['*claude*' => Process::result(output: json_encode([
        ['video_id' => 'vidReq001', 'classification' => 'likely_recipe', 'score' => 95],
    ]))]);

    app(ClassifyDiscoveredVideos::class)->handle($request);

    expect($video->fresh()->score)->toBe(95);

    Process::assertRan(function ($process) {
        $prompt = $process->input;

        return str_contains($prompt, 'hibachi for 4')
            && str_contains($prompt, 'DO NOT apply here')
            && ! str_contains($prompt, 'Total time (prep + cook) 30 minutes or less');
    });
});

it('keeps the two classification lanes apart', function () {
    $request = RecipeRequest::factory()->create();

    $requested = DiscoveredVideo::factory()->create([
        'video_id' => 'vidRequested',
        'recipe_request_id' => $request->id,
        'classification' => null,
    ]);

    $scheduled = DiscoveredVideo::factory()->create([
        'video_id' => 'vidScheduled',
        'recipe_request_id' => null,
        'classification' => null,
    ]);

    Process::fake(['*claude*' => Process::result(output: json_encode([
        ['video_id' => 'vidRequested', 'classification' => 'likely_recipe', 'score' => 90],
        ['video_id' => 'vidScheduled', 'classification' => 'likely_recipe', 'score' => 90],
    ]))]);

    // A scheduled pass must not touch the request's rows, and vice versa.
    app(ClassifyDiscoveredVideos::class)->handle();

    expect($scheduled->fresh()->classification)->not->toBeNull()
        ->and($requested->fresh()->classification)->toBeNull();

    app(ClassifyDiscoveredVideos::class)->handle($request);

    expect($requested->fresh()->classification)->not->toBeNull();
});

it('names the household limits in the request video rubric as the ones that do not apply', function () {
    app(HouseholdPreferences::class)->setWeekdayLimits(45, 12);
    $request = RecipeRequest::factory()->create(['query' => 'hibachi for 4']);
    DiscoveredVideo::factory()->create(['video_id' => 'vidReq002', 'recipe_request_id' => $request->id, 'classification' => null]);

    Process::fake(['*claude*' => Process::result(output: json_encode([
        ['video_id' => 'vidReq002', 'classification' => 'likely_recipe', 'score' => 95],
    ]))]);

    app(ClassifyDiscoveredVideos::class)->handle($request);

    Process::assertRan(fn ($process) => str_contains($process->input, '(45 minutes, 12 ingredients) DO NOT apply here'));
});
