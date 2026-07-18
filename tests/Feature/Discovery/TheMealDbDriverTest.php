<?php

use App\Discovery\TheMealDbDriver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function theMealDbFixture(): array
{
    return json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/themealdb-random.json')),
        true,
    );
}

it('maps a TheMealDB payload onto the candidate schema', function () {
    Http::fake([
        'www.themealdb.com/*' => Http::response(theMealDbFixture()),
    ]);

    $candidates = app(TheMealDbDriver::class)->discover(1);

    expect($candidates)->toHaveCount(1);

    $candidate = $candidates[0];

    expect($candidate['title'])->toBe('Teriyaki Chicken Casserole')
        ->and($candidate['description'])->toBe('Japanese Chicken recipe from TheMealDB.')
        ->and($candidate['meal_type'])->toBe('any')
        ->and($candidate['prep_minutes'])->toBe(0)
        ->and($candidate['cook_minutes'])->toBe(0)
        ->and($candidate['servings'])->toBe(4)
        ->and($candidate['instructions'])->toContain('Preheat oven to 350 degrees F')
        ->and($candidate['cuisine'])->toBe('Japanese')
        ->and($candidate['tags'])->toBe(['Meat', 'Casserole'])
        ->and($candidate['source_url'])->toBe('https://www.tablefortwoblog.com/teriyaki-chicken-casserole/');

    // strMeasure/strIngredient pairs become pivot-shaped rows via the paste parser.
    expect($candidate['ingredients'])->toHaveCount(9)
        ->and($candidate['ingredients'][0])->toBe([
            'qty' => 0.75,
            'unit' => 'cup',
            'name' => 'soy sauce',
            'note' => null,
        ])
        ->and($candidate['ingredients'][6])->toBe([
            'qty' => 2.0,
            'unit' => null,
            'name' => 'chicken breasts',
            'note' => null,
        ]);
});

it('calls the random endpoint once per requested candidate and dedupes repeats', function () {
    Http::fake([
        'www.themealdb.com/*' => Http::response(theMealDbFixture()),
    ]);

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
