<?php

use App\Actions\Discovery\ClassifyDiscoveredVideos;
use App\Discovery\AnthropicDriver;
use App\Enums\RecipeStatus;
use App\Models\DiscoveredVideo;
use App\Models\Recipe;
use App\Models\RecipeRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * The whole point of a request is that the weeknight caps stop applying. Both
 * prompts carry those caps, so both are covered here: exempting only one of
 * them leaves the other silently filtering out the dish that was asked for.
 */
function requestCandidate(): array
{
    return [
        'title' => 'Hibachi Steak and Fried Rice',
        'description' => 'Flat-top steak with garlic butter and fried rice.',
        'meal_type' => 'dinner',
        'prep_minutes' => 25,
        'cook_minutes' => 35,
        'servings' => 4,
        'instructions' => "1. Heat the griddle.\n2. Sear the steak.\n3. Fry the rice.",
        'cuisine' => 'Japanese',
        'tags' => ['griddle'],
        'source_url' => 'https://example.com/recipes/hibachi',
        'ingredients' => [
            ['qty' => 700, 'unit' => 'g', 'name' => 'sirloin steak', 'note' => 'cubed'],
            ['qty' => 4, 'unit' => 'cup', 'name' => 'cooked rice', 'note' => 'day-old'],
        ],
    ];
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Http::fake(['example.com/*' => Http::response()]);
});

it('drops the time and ingredient caps from the generation prompt on a request', function () {
    $request = RecipeRequest::factory()->create(['query' => 'hibachi for 4 on a flat-top griddle']);

    Process::fake(['*claude*' => Process::result(output: json_encode([requestCandidate()]))]);

    app(AnthropicDriver::class)->discoverFor($request, 1);

    Process::assertRan(function ($process) {
        $prompt = $process->command[2];

        return str_contains($prompt, 'hibachi for 4 on a flat-top griddle')
            && str_contains($prompt, 'NO time limit and NO ingredient limit')
            && ! str_contains($prompt, 'must be 30 minutes or less')
            && ! str_contains($prompt, '10 ingredients or fewer');
    });
});

it('keeps the caps in the generation prompt for scheduled discovery', function () {
    Process::fake(['*claude*' => Process::result(output: json_encode([requestCandidate()]))]);

    app(AnthropicDriver::class)->discover(1);

    Process::assertRan(function ($process) {
        $prompt = $process->command[2];

        return str_contains($prompt, 'must be 30 minutes or less')
            && str_contains($prompt, '10 ingredients or fewer')
            && ! str_contains($prompt, 'NO time limit');
    });
});

it('omits the over-30-minutes rejection theme on a request but keeps it when scheduled', function () {
    // Two rejected long recipes is what earns the theme a line in the prompt.
    Recipe::factory()->count(2)->create([
        'status' => RecipeStatus::Rejected,
        'prep_minutes' => 40,
        'cook_minutes' => 30,
    ]);

    $request = RecipeRequest::factory()->create();

    Process::fake(['*claude*' => Process::result(output: json_encode([requestCandidate()]))]);

    app(AnthropicDriver::class)->discover(1);
    Process::assertRan(fn ($process) => str_contains($process->command[2], 'recipes over 30 minutes total'));

    Process::fake(['*claude*' => Process::result(output: json_encode([requestCandidate()]))]);

    app(AnthropicDriver::class)->discoverFor($request, 1);
    Process::assertRan(fn ($process) => ! str_contains($process->command[2], 'recipes over 30 minutes total'));
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
        $prompt = $process->command[2];

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
