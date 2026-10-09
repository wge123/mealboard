<?php

use App\Discovery\TheMealDbDriver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function theMealDbFixture(): array
{
    return json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/themealdb-random.json')),
        true,
    );
}

function shapedMealDbOutput(array $overrides = []): array
{
    return array_merge([
        'title' => 'x',
        'description' => 'Japanese Chicken recipe from TheMealDB.',
        'meal_type' => 'dinner',
        'prep_minutes' => 15,
        'cook_minutes' => 45,
        'servings' => 4,
        'cuisine' => 'Japanese',
        'tags' => [],
        'source_url' => 'https://example.com/x',
        'tools' => [['alternatives' => ['casserole dish', 'baking dish'], 'count' => 1]],
        'ingredients' => [
            ['qty' => 0.75, 'unit' => 'cup', 'name' => 'soy sauce', 'prep_note' => null],
        ],
        'steps' => ['Preheat the oven.', 'Bake the casserole.'],
    ], $overrides);
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
});

it('sends each meal through the shaping pass and returns shaped, checked candidates', function () {
    Http::fake([
        'www.themealdb.com/*' => Http::response(theMealDbFixture()),
    ]);
    Process::fake(['*' => Process::result(output: json_encode(shapedMealDbOutput()))]);

    $candidates = app(TheMealDbDriver::class)->discover(1);

    expect($candidates)->toHaveCount(1);

    $candidate = $candidates[0];

    expect($candidate['title'])->toBe('Teriyaki Chicken Casserole')
        ->and($candidate['description'])->toBe('Japanese Chicken recipe from TheMealDB.')
        ->and($candidate['cuisine'])->toBe('Japanese')
        ->and($candidate['tags'])->toBe(['Meat', 'Casserole'])
        ->and($candidate['source_url'])->toBe('https://www.tablefortwoblog.com/teriyaki-chicken-casserole/')
        // TheMealDB knows no timings, servings or meal type: the model's estimates stand.
        ->and($candidate['prep_minutes'])->toBe(15)
        ->and($candidate['cook_minutes'])->toBe(45)
        ->and($candidate['meal_type'])->toBe('dinner')
        ->and($candidate['tools'])->toHaveCount(1)
        ->and($candidate['steps'])->toBe(['Preheat the oven.', 'Bake the casserole.'])
        ->and($candidate)->not->toHaveKey('instructions');

    // The raw ingredient lines (strMeasure/strIngredient via the paste parser)
    // and the raw method reach the model.
    Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'soy sauce')
        && str_contains($process->command[2] ?? '', 'Preheat oven to 350 degrees F'));
});

it('drops a meal whose shaped output fails the check, without a retry', function () {
    Http::fake([
        'www.themealdb.com/*' => Http::response(theMealDbFixture()),
    ]);
    Process::fake(['*' => Process::result(output: json_encode(shapedMealDbOutput(['steps' => []])))]);

    $candidates = app(TheMealDbDriver::class)->discover(1);

    expect($candidates)->toBe([]);
    Process::assertRanTimes(fn () => true, 1);
});

it('calls the random endpoint once per requested candidate and dedupes repeats', function () {
    Http::fake([
        'www.themealdb.com/*' => Http::response(theMealDbFixture()),
    ]);
    Process::fake(['*' => Process::result(output: json_encode(shapedMealDbOutput()))]);

    $candidates = app(TheMealDbDriver::class)->discover(3);

    Http::assertSentCount(3);

    // The fake returns the same meal every time; identical ids collapse to one candidate.
    expect($candidates)->toHaveCount(1);
});

it('throws when TheMealDB responds with an error', function () {
    Http::fake([
        'www.themealdb.com/*' => Http::response(null, 500),
    ]);

    app(TheMealDbDriver::class)->discover(1);
})->throws(RequestException::class);
