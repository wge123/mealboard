<?php

use App\Enums\RecipeStatus;
use App\Enums\RequestStatus;
use App\Models\DiscoveredVideo;
use App\Models\Recipe;
use App\Models\RecipeRequest;
use App\Support\HouseholdPreferences;
use Illuminate\Support\Facades\Process;

function hibachiCandidateJson(string $title = 'Hibachi Steak and Fried Rice'): string
{
    return json_encode([[
        'title' => $title,
        'description' => 'Flat-top steak with garlic butter and fried rice.',
        'meal_type' => 'dinner',
        'prep_minutes' => 25,
        // Deliberately past the weeknight cap: a requested dish is exempt.
        'cook_minutes' => 35,
        'servings' => 4,
        'cuisine' => 'Japanese',
        'tags' => ['griddle'],
        'source_url' => 'https://example.com/recipes/hibachi',
        'tools' => [
            ['alternatives' => ['flat-top griddle', 'large skillet'], 'count' => 1],
        ],
        'ingredients' => [
            ['qty' => 700, 'unit' => 'g', 'name' => 'sirloin steak', 'prep_note' => 'cubed'],
            ['qty' => 4, 'unit' => 'cup', 'name' => 'cooked rice', 'prep_note' => 'day-old'],
        ],
        'steps' => ['Heat the griddle.', 'Sear the steak.', 'Fry the rice.'],
    ]]);
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    config()->set('mealboard.yt_dlp', '/fake/bin/yt-dlp');
    config()->set('mealboard.yt_python', '/fake/bin/python3');
});

/**
 * One YouTube search hit, classified likely_recipe and extracted into $title.
 */
function fakeHibachiVideo(string $title = 'Griddle Hibachi from the Video'): void
{
    Process::fake([
        '*yt-dlp*' => Process::result(output: json_encode([
            'id' => 'vidHibachi9', 'title' => 'Blackstone Hibachi', 'channel_id' => 'UCc1',
        ])),
        '*python3*' => Process::result(output: 'heat the griddle and sear the steak'),
        '*claude*' => Process::sequence()
            ->push(json_encode([['video_id' => 'vidHibachi9', 'classification' => 'likely_recipe', 'score' => 92]]))
            ->push(json_encode(json_decode(hibachiCandidateJson($title), true)[0])),
    ]);
}

it('stores a requested recipe that would breach the weeknight caps', function () {
    fakeHibachiVideo();

    $this->artisan('recipes:request', ['query' => 'hibachi for 4 on a flat-top griddle'])
        ->assertSuccessful();

    $request = RecipeRequest::sole();
    $recipe = Recipe::sole();

    expect($request->query)->toBe('hibachi for 4 on a flat-top griddle')
        ->and($request->status)->toBe(RequestStatus::Completed)
        ->and($request->candidates_found)->toBe(1)
        ->and($request->completed_at)->not->toBeNull()
        ->and($recipe->recipe_request_id)->toBe($request->id)
        ->and($recipe->status)->toBe(RecipeStatus::Pending)
        ->and($recipe->prep_minutes + $recipe->cook_minutes)->toBeGreaterThan(30)
        ->and(DiscoveredVideo::sole()->recipe_request_id)->toBe($request->id);
});

it('stores only YouTube candidates and never asks claude to write a recipe', function () {
    fakeHibachiVideo();

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::pluck('source_url')->all())->toBe(['https://www.youtube.com/watch?v=vidHibachi9']);

    // Only the classification and the video extraction ran; no third call
    // asked for candidate recipes.
    Process::assertRanTimes(fn ($process) => ($process->command[0] ?? '') === '/fake/bin/claude', 2);
});

it('fails with a non-zero exit when the YouTube search dies', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(errorOutput: 'ERROR: search unavailable', exitCode: 1),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertFailed();

    $request = RecipeRequest::sole();

    expect($request->status)->toBe(RequestStatus::Failed)
        ->and($request->error)->toContain('youtube:')
        ->and(Recipe::count())->toBe(0);

    Process::assertNotRan(fn ($process) => str_contains(implode(' ', $process->command), 'claude'));
});

it('succeeds without storing anything when the request only matches known recipes', function () {
    Recipe::factory()->create(['title' => 'Griddle Hibachi from the Video']);

    fakeHibachiVideo();

    // Zero NEW candidates is an answer, not a failure: the search ran fine.
    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::count())->toBe(1)
        ->and(RecipeRequest::sole()->candidates_found)->toBe(0)
        ->and(RecipeRequest::sole()->status)->toBe(RequestStatus::Completed);
});

it('succeeds with nothing stored when the search finds no videos', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(output: ''),
        // A model that would happily write a hibachi recipe if asked.
        '*claude*' => Process::result(output: hibachiCandidateJson()),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::count())->toBe(0)
        ->and(RecipeRequest::sole()->status)->toBe(RequestStatus::Completed);

    Process::assertNotRan(fn ($process) => str_contains(implode(' ', $process->command), 'claude'));
});

it('rejects an empty request without touching the search', function () {
    Process::fake();

    $this->artisan('recipes:request', ['query' => '   '])->assertFailed();

    expect(RecipeRequest::count())->toBe(0);
    Process::assertNothingRan();
});

it('stores a shaped recipe from the youtube lane', function () {
    fakeHibachiVideo();

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    $recipe = Recipe::sole();

    expect($recipe->title)->toBe('Griddle Hibachi from the Video')
        ->and($recipe->hasShape())->toBeTrue()
        ->and($recipe->recipeTools)->toHaveCount(1)
        ->and($recipe->cookingSteps->pluck('text')->all())->toBe(['Heat the griddle.', 'Sear the steak.', 'Fry the rice.'])
        ->and($recipe->ingredients->firstWhere('name', 'sirloin steak')->pivot->note)->toBe('cubed')
        ->and($recipe->source_url)->toBe('https://www.youtube.com/watch?v=vidHibachi9');
});

it('stores a requested recipe over the weekday limits but drops one with an avoided ingredient', function () {
    $preferences = app(HouseholdPreferences::class);
    $preferences->setWeekdayLimits(10, 1);
    $preferences->avoid('anchovies');

    $withAnchovies = json_decode(hibachiCandidateJson('Anchovy Hibachi'), true)[0];
    $withAnchovies['ingredients'][] = ['qty' => 4, 'unit' => null, 'name' => 'Anchovies', 'prep_note' => 'chopped'];

    Process::fake([
        '*yt-dlp*' => Process::sequence()->push(
            json_encode(['id' => 'vidLong00001', 'title' => 'Long Hibachi', 'channel_id' => 'UCc1'])
            ."\n".json_encode(['id' => 'vidAnch00001', 'title' => 'Anchovy Hibachi', 'channel_id' => 'UCc1']),
        ),
        '*python3*' => Process::result(output: 'heat the griddle and sear the steak'),
        '*claude*' => Process::sequence()
            ->push(json_encode([
                ['video_id' => 'vidLong00001', 'classification' => 'likely_recipe', 'score' => 92],
                ['video_id' => 'vidAnch00001', 'classification' => 'likely_recipe', 'score' => 90],
            ]))
            ->push(json_encode(json_decode(hibachiCandidateJson('Long Hibachi'), true)[0]))
            ->push(json_encode($withAnchovies)),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::pluck('title')->all())->toBe(['Long Hibachi'])
        ->and(RecipeRequest::sole()->candidates_found)->toBe(1);
});
