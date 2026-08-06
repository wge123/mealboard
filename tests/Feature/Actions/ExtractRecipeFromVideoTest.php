<?php

use App\Actions\Discovery\ExtractRecipeFromVideo;
use App\Exceptions\DiscoveryEnvironmentException;
use App\Models\DiscoveredVideo;
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
        'instructions' => "1. Boil noodles.\n2. Sizzle garlic in butter.\n3. Toss together.",
        'cuisine' => null,
        'tags' => ['quick'],
        'source_url' => null,
        'ingredients' => [
            ['qty' => 200, 'unit' => 'g', 'name' => 'noodles', 'note' => null],
            ['qty' => 3, 'unit' => null, 'name' => 'garlic cloves', 'note' => 'minced'],
        ],
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
        ->and($candidate['ingredients'])->toHaveCount(2);

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

it('records a schema-invalid claude response as a failure', function () {
    $video = DiscoveredVideo::factory()->likelyRecipe()->create();

    Process::fake([
        '*python3*' => Process::result(output: 'a transcript'),
        '*claude*' => Process::result(output: json_encode(validVideoRecipeObject(['title' => '']))),
    ]);

    expect(app(ExtractRecipeFromVideo::class)->handle($video))->toBeNull();

    expect($video->refresh()->error)->toContain('failed schema validation')
        ->and($video->processed_at)->toBeNull();
});
