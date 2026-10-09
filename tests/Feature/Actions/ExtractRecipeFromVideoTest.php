<?php

use App\Actions\Discovery\ExtractRecipeFromVideo;
use App\Discovery\RecipeOutputFormat;
use App\Exceptions\DiscoveryEnvironmentException;
use App\Models\DiscoveredVideo;
use App\Support\KitchenToolInventory;
use Illuminate\Support\Facades\Process;

function validVideoRecipeObject(array $overrides = []): array
{
    return array_merge([
        'title' => 'Garlic Butter Noodles',
        'description' => 'Fifteen-minute pantry noodles with lots of garlic.',
        'meal_type' => 'dinner',
        'prep_minutes' => 5,
        'cook_minutes' => 10,
        'servings' => 2,
        'cuisine' => null,
        'tags' => ['quick'],
        'source_url' => null,
        'tools' => [
            ['alternatives' => ['large pot', 'saucepan'], 'count' => 1],
            ['alternatives' => ['skillet'], 'count' => 1],
        ],
        'ingredients' => [
            ['qty' => 200, 'unit' => 'g', 'name' => 'noodles', 'prep_note' => null],
            ['qty' => 3, 'unit' => null, 'name' => 'garlic cloves', 'prep_note' => 'minced'],
        ],
        'steps' => ['Boil the noodles.', 'Sizzle the garlic in butter.', 'Toss together.'],
    ], $overrides);
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    config()->set('mealboard.yt_python', '/fake/bin/python3');
});

it('extracts a validated candidate from the transcript and stamps the row', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create([
        'video_id' => 'vidNoodle01',
        'title' => 'GARLIC NOODLES in 15 minutes',
    ]);

    Process::fake([
        '*python3*' => Process::result(output: 'boil the noodles then sizzle the garlic'),
        '*claude*' => Process::result(output: json_encode(validVideoRecipeObject())),
    ]);

    $candidate = app(ExtractRecipeFromVideo::class)->handle($video);

    expect($candidate)->not->toBeNull()
        ->and($candidate['title'])->toBe('Garlic Butter Noodles')
        ->and($candidate['source_url'])->toBe('https://www.youtube.com/watch?v=vidNoodle01')
        ->and($candidate['ingredients'])->toHaveCount(2)
        ->and($candidate['tools'][0])->toBe(['alternatives' => ['large pot', 'saucepan'], 'count' => 1])
        ->and($candidate['steps'])->toBe(['Boil the noodles.', 'Sizzle the garlic in butter.', 'Toss together.']);

    expect($video->refresh()->processed_at)->not->toBeNull()
        ->and($video->error)->toBeNull();

    // Thin python subprocess: video id passed as argv, transcript only.
    Process::assertRan(function ($process) {
        return $process->command[0] === '/fake/bin/python3'
            && $process->command[1] === '-c'
            && str_contains($process->command[2], 'youtube_transcript_api')
            && $process->command[3] === 'vidNoodle01';
    });

    // The claude prompt carries the transcript and video context.
    Process::assertRan(function ($process) {
        return $process->command[0] === '/fake/bin/claude'
            && str_contains($process->command[2], 'boil the noodles then sizzle the garlic')
            && str_contains($process->command[2], 'GARLIC NOODLES in 15 minutes');
    });
});

it('puts the shared output format and the kinds list in the extraction prompt', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake([
        '*python3*' => Process::result(output: 'a transcript'),
        '*claude*' => Process::result(output: json_encode(validVideoRecipeObject())),
    ]);

    app(ExtractRecipeFromVideo::class)->handle($video);

    Process::assertRan(function ($process) {
        $prompt = $process->command[2] ?? '';

        return ($process->command[0] ?? '') === '/fake/bin/claude'
            && str_contains($prompt, RecipeOutputFormat::schema('null'))
            && str_contains($prompt, "Kitchen tool words you may use:\n".app(KitchenToolInventory::class)->kinds()->implode(', '))
            && ! str_contains($prompt, 'Tools the household owns')
            && ! str_contains($prompt, 'Prefer recipes that need no');
    });
});

it('retries once with its own output and the errors, then stores the corrected recipe', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake([
        '*python3*' => Process::result(output: 'a transcript'),
        '*claude*' => Process::sequence()
            ->push(json_encode(validVideoRecipeObject(['title' => 'Almost Noodles', 'steps' => []])))
            ->push(json_encode(validVideoRecipeObject())),
    ]);

    $candidate = app(ExtractRecipeFromVideo::class)->handle($video);

    expect($candidate['title'])->toBe('Garlic Butter Noodles');

    expect($video->refresh()->error)->toBeNull()
        ->and($video->processed_at)->not->toBeNull();

    Process::assertRanTimes(fn ($process) => ($process->command[0] ?? '') === '/fake/bin/claude', 2);

    // Only the retry prompt can contain the first output and its errors.
    Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'Almost Noodles')
        && str_contains($process->command[2], 'steps: at least one step is required'));
});

it('records a captionless video failure without calling claude', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake([
        '*python3*' => Process::result(
            output: '',
            errorOutput: 'youtube_transcript_api._errors.TranscriptsDisabled',
            exitCode: 1,
        ),
    ]);

    expect(app(ExtractRecipeFromVideo::class)->handle($video))->toBeNull();

    expect($video->refresh()->error)->toContain('transcript fetch failed')
        ->and($video->error)->toContain('TranscriptsDisabled')
        ->and($video->processed_at)->toBeNull();

    Process::assertDidntRun(fn ($process) => $process->command[0] === '/fake/bin/claude');
});

it('does not blacklist the video when the interpreter itself cannot run', function () {
    // 126/127 means yt_python is gone, not that the video is captionless. The
    // per-video path would write an error on the row, and YouTubeDriver filters
    // on whereNull('error'), so a healthy video would be excluded from every
    // future run because of a broken absolute path in config.
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake([
        '*python3*' => Process::result(
            output: '',
            errorOutput: 'sh: /fake/bin/python3: cannot execute: No such file or directory',
            exitCode: 126,
        ),
    ]);

    expect(fn () => app(ExtractRecipeFromVideo::class)->handle($video))
        ->toThrow(DiscoveryEnvironmentException::class, 'could not be executed');

    expect($video->refresh()->error)->toBeNull()
        ->and($video->processed_at)->toBeNull();
});

it('records an empty transcript as a failure', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake(['*python3*' => Process::result(output: "  \n")]);

    expect(app(ExtractRecipeFromVideo::class)->handle($video))->toBeNull();

    expect($video->refresh()->error)->toContain('transcript was empty')
        ->and($video->processed_at)->toBeNull();
});

it('records the error on the video row when the retry also fails', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake([
        '*python3*' => Process::result(output: 'a transcript'),
        '*claude*' => Process::result(output: json_encode(validVideoRecipeObject(['title' => '']))),
    ]);

    expect(app(ExtractRecipeFromVideo::class)->handle($video))->toBeNull();

    expect($video->refresh()->error)->toContain('failed schema validation')
        ->and($video->error)->toContain('title: must be a non-empty string')
        ->and($video->processed_at)->toBeNull();

    // One retry, never a third call.
    Process::assertRanTimes(fn ($process) => ($process->command[0] ?? '') === '/fake/bin/claude', 2);
});
