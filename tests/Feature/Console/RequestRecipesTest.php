<?php

use App\Enums\RecipeStatus;
use App\Enums\RequestStatus;
use App\Models\DiscoveredVideo;
use App\Models\Recipe;
use App\Models\RecipeRequest;
use Illuminate\Support\Facades\Http;
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
    Http::fake(['example.com/*' => Http::response()]);
});

it('stores a requested recipe that would breach the weeknight caps', function () {
    Process::fake([
        // No search hits: the claude lane alone answers this request.
        '*yt-dlp*' => Process::result(output: ''),
        '*claude*' => Process::result(output: hibachiCandidateJson()),
    ]);

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
        ->and($recipe->prep_minutes + $recipe->cook_minutes)->toBeGreaterThan(30);
});

it('runs both lanes and keeps what each returns', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(output: json_encode([
            'id' => 'vidHibachi9', 'title' => 'Blackstone Hibachi', 'channel_id' => 'UCc1',
        ])),
        '*python3*' => Process::result(output: 'heat the griddle and sear the steak'),
        '*claude*' => Process::sequence()
            // Classification of the one search hit, then the video extraction,
            // then the claude lane's own candidate.
            ->push(json_encode([['video_id' => 'vidHibachi9', 'classification' => 'likely_recipe', 'score' => 92]]))
            ->push(json_encode(json_decode(hibachiCandidateJson('Griddle Hibachi from the Video'), true)[0]))
            ->push(hibachiCandidateJson('Hibachi Steak from Claude')),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::count())->toBe(2)
        ->and(Recipe::pluck('title')->all())
        ->toContain('Griddle Hibachi from the Video', 'Hibachi Steak from Claude')
        ->and(DiscoveredVideo::sole()->recipe_request_id)->toBe(RecipeRequest::sole()->id);
});

it('completes with a partial failure recorded when only one lane dies', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(errorOutput: 'ERROR: search unavailable', exitCode: 1),
        '*claude*' => Process::result(output: hibachiCandidateJson()),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    $request = RecipeRequest::sole();

    expect($request->status)->toBe(RequestStatus::Completed)
        ->and($request->error)->toContain('youtube:')
        ->and(Recipe::count())->toBe(1);
});

it('fails with a non-zero exit when every lane dies', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(errorOutput: 'ERROR: search unavailable', exitCode: 1),
        '*claude*' => Process::result(errorOutput: 'claude exploded', exitCode: 1),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertFailed();

    $request = RecipeRequest::sole();

    expect($request->status)->toBe(RequestStatus::Failed)
        ->and($request->error)->toContain('youtube:')
        ->and($request->error)->toContain('claude:')
        ->and(Recipe::count())->toBe(0);
});

it('succeeds without storing anything when the request only matches known recipes', function () {
    Recipe::factory()->create(['title' => 'Hibachi Steak and Fried Rice']);

    Process::fake([
        '*yt-dlp*' => Process::result(output: ''),
        '*claude*' => Process::result(output: hibachiCandidateJson()),
    ]);

    // Zero NEW candidates is an answer, not a failure: the lanes ran fine.
    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::count())->toBe(1)
        ->and(RecipeRequest::sole()->candidates_found)->toBe(0)
        ->and(RecipeRequest::sole()->status)->toBe(RequestStatus::Completed);
});

it('rejects an empty request without touching the lanes', function () {
    Process::fake();

    $this->artisan('recipes:request', ['query' => '   '])->assertFailed();

    expect(RecipeRequest::count())->toBe(0);
    Process::assertNothingRan();
});

it('stores a shaped recipe from the claude lane with tools, prep notes and steps', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(output: ''),
        '*claude*' => Process::result(output: hibachiCandidateJson()),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    $recipe = Recipe::sole();

    expect($recipe->hasShape())->toBeTrue()
        ->and($recipe->recipeTools)->toHaveCount(1)
        ->and($recipe->cookingSteps->pluck('text')->all())->toBe(['Heat the griddle.', 'Sear the steak.', 'Fry the rice.'])
        ->and($recipe->ingredients->firstWhere('name', 'sirloin steak')->pivot->note)->toBe('cubed');
});

it('stores a shaped recipe from the youtube lane', function () {
    Process::fake([
        '*yt-dlp*' => Process::result(output: json_encode([
            'id' => 'vidHibachi9', 'title' => 'Blackstone Hibachi', 'channel_id' => 'UCc1',
        ])),
        '*python3*' => Process::result(output: 'heat the griddle and sear the steak'),
        '*claude*' => Process::sequence()
            ->push(json_encode([['video_id' => 'vidHibachi9', 'classification' => 'likely_recipe', 'score' => 92]]))
            ->push(json_encode(json_decode(hibachiCandidateJson('Griddle Hibachi from the Video'), true)[0]))
            ->push('[]'),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    $recipe = Recipe::sole();

    expect($recipe->title)->toBe('Griddle Hibachi from the Video')
        ->and($recipe->hasShape())->toBeTrue()
        ->and($recipe->source_url)->toBe('https://www.youtube.com/watch?v=vidHibachi9');
});

it('retries the claude lane once with its own output and the errors', function () {
    $broken = json_decode(hibachiCandidateJson('Broken Hibachi'), true);
    $broken[0]['steps'] = [];

    Process::fake([
        '*yt-dlp*' => Process::result(output: ''),
        '*claude*' => Process::sequence()
            ->push(json_encode($broken))
            ->push(hibachiCandidateJson('Fixed Hibachi')),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::pluck('title')->all())->toBe(['Fixed Hibachi']);

    Process::assertRanTimes(fn ($process) => ($process->command[0] ?? '') === '/fake/bin/claude', 2);

    // Only the retry prompt can contain the first output and its errors.
    Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'Broken Hibachi')
        && str_contains($process->command[2], 'steps: at least one step is required'));
});

it('drops a claude-lane candidate that fails the check twice', function () {
    $broken = json_decode(hibachiCandidateJson('Broken Hibachi'), true);
    $broken[0]['steps'] = [];

    Process::fake([
        '*yt-dlp*' => Process::result(output: ''),
        '*claude*' => Process::result(output: json_encode($broken)),
    ]);

    $this->artisan('recipes:request', ['query' => 'hibachi'])->assertSuccessful();

    expect(Recipe::count())->toBe(0)
        ->and(RecipeRequest::sole()->status)->toBe(RequestStatus::Completed);

    Process::assertRanTimes(fn ($process) => ($process->command[0] ?? '') === '/fake/bin/claude', 2);
});
