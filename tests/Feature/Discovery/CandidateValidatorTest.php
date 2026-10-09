<?php

use App\Discovery\CandidateValidator;

function shapedItem(array $overrides = []): array
{
    return array_merge([
        'title' => 'Lemon Garlic Salmon Bowls',
        'description' => 'Quick pan-seared salmon over rice.',
        'meal_type' => 'dinner',
        'prep_minutes' => 10,
        'cook_minutes' => 15,
        'servings' => 2,
        'cuisine' => 'Mediterranean',
        'tags' => ['fish'],
        'source_url' => 'https://example.com/salmon',
        'tools' => [
            ['alternatives' => ['skillet', 'frying pan'], 'count' => 1],
        ],
        'ingredients' => [
            ['qty' => 2, 'unit' => null, 'name' => 'salmon fillets', 'prep_note' => 'patted dry'],
            ['qty' => 1, 'unit' => 'tbsp', 'name' => 'olive oil', 'prep_note' => null],
        ],
        'steps' => ['Sear the salmon.', 'Assemble the bowls.'],
    ], $overrides);
}

it('returns the shaped candidate when every check passes', function () {
    $check = (new CandidateValidator)->check(shapedItem());

    expect($check->passes())->toBeTrue()
        ->and($check->errors)->toBe([])
        ->and($check->candidate['tools'])->toBe([['alternatives' => ['skillet', 'frying pan'], 'count' => 1]])
        ->and($check->candidate['steps'])->toBe(['Sear the salmon.', 'Assemble the bowls.'])
        ->and($check->candidate['ingredients'][0])->toBe([
            'qty' => 2.0, 'unit' => null, 'name' => 'salmon fillets', 'prep_note' => 'patted dry',
        ])
        ->and($check->candidate)->not->toHaveKey('instructions');
});

it('rejects with a readable error', function (array $overrides, string $expected) {
    $check = (new CandidateValidator)->check(shapedItem($overrides));

    expect($check->passes())->toBeFalse()
        ->and($check->candidate)->toBeNull()
        ->and($check->errors)->toContain($expected);
})->with([
    'no tools' => [['tools' => []], 'tools: at least one tool is required'],
    'missing tools' => [['tools' => null], 'tools: at least one tool is required'],
    'tools with only empty alternatives' => [['tools' => [['alternatives' => ['  ', '']]]], 'tools: at least one tool is required'],
    'no ingredients' => [['ingredients' => []], 'ingredients: at least one ingredient is required'],
    'no steps' => [['steps' => []], 'steps: at least one step is required'],
    'only blank steps' => [['steps' => ['', '   ']], 'steps: at least one step is required'],
    'blank title' => [['title' => ' '], 'title: must be a non-empty string'],
    'blank description' => [['description' => ''], 'description: must be a non-empty string'],
    'bad meal type' => [['meal_type' => 'brunch'], 'meal_type: must be one of breakfast, lunch, dinner, any'],
    'non-numeric minutes' => [['prep_minutes' => 'soon'], 'prep_minutes: must be a number'],
    'no source url' => [['source_url' => null], 'source_url: must be a valid http(s) URL'],
    'ftp source url' => [['source_url' => 'ftp://example.com/x'], 'source_url: must be a valid http(s) URL'],
    'nameless ingredient' => [['ingredients' => [['qty' => 1, 'unit' => null]]], 'ingredients[0].name: must be a non-empty string'],
    'non-numeric ingredient qty' => [['ingredients' => [['name' => 'salt', 'qty' => 'lots']]], 'ingredients[0].qty: must be a number or null'],
    'non-string step' => [['steps' => ['Cook.', 42]], 'steps[1]: must be a string'],
    'negative tool count' => [['tools' => [['alternatives' => ['wok'], 'count' => -2]]], 'tools[0].count: must be a whole number of 1 or more'],
]);

it('reports every error at once, not just the first', function () {
    $check = (new CandidateValidator)->check(shapedItem(['title' => '', 'tools' => [], 'steps' => []]));

    expect($check->errors)->toHaveCount(3);
});

it('rejects something that is not an object', function () {
    expect((new CandidateValidator)->check('nope')->errors)->toBe(['candidate: must be a JSON object']);
});

it('repairs rather than rejects', function () {
    $check = (new CandidateValidator)->check(shapedItem([
        'tools' => [
            ['alternatives' => ['  skillet ', '', '   ', 'frying pan'], 'count' => 0],
            ['alternatives' => ['Frying Pan', 'SKILLET']],
            ['alternatives' => ['whisk']],
            ['alternatives' => ['sheet pan'], 'count' => '2'],
        ],
        'steps' => ['  Sear.  ', '', '   ', 'Serve.'],
        'ingredients' => [['name' => ' salmon ', 'prep_note' => '  ']],
    ]));

    expect($check->passes())->toBeTrue()
        ->and($check->candidate['tools'])->toBe([
            ['alternatives' => ['skillet', 'frying pan'], 'count' => 1],
            ['alternatives' => ['whisk'], 'count' => 1],
            ['alternatives' => ['sheet pan'], 'count' => 2],
        ])
        ->and($check->candidate['steps'])->toBe(['Sear.', 'Serve.'])
        ->and($check->candidate['ingredients'][0]['name'])->toBe('salmon')
        ->and($check->candidate['ingredients'][0]['prep_note'])->toBeNull();
});

it('does not look for prep text inside a step', function () {
    $check = (new CandidateValidator)->check(shapedItem(['steps' => ['Dice the onion and mince the garlic.']]));

    expect($check->passes())->toBeTrue();
});
