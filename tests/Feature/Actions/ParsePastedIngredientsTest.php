<?php

use App\Actions\Recipes\ParsePastedIngredients;

it('parses qty, unit, name, and prep note', function () {
    $rows = app(ParsePastedIngredients::class)->handle('2 cups diced onion');

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBe([
            'qty' => 2.0,
            'unit' => 'cup',
            'name' => 'onion',
            'note' => 'diced',
        ]);
});

it('parses fraction quantities', function () {
    $rows = app(ParsePastedIngredients::class)->handle('1/2 tsp salt');

    expect($rows[0])->toBe([
        'qty' => 0.5,
        'unit' => 'tsp',
        'name' => 'salt',
        'note' => null,
    ]);
});

it('turns unmatched lines into name-only rows', function () {
    $rows = app(ParsePastedIngredients::class)->handle('eggs');

    expect($rows[0])->toBe([
        'qty' => null,
        'unit' => null,
        'name' => 'eggs',
        'note' => null,
    ]);
});

it('parses decimals, plural units, and comma notes', function () {
    $rows = app(ParsePastedIngredients::class)->handle('1.5 lbs chicken thighs, trimmed');

    expect($rows[0])->toBe([
        'qty' => 1.5,
        'unit' => 'lb',
        'name' => 'chicken thighs',
        'note' => 'trimmed',
    ]);
});

it('leaves the unit empty when the token is not in the normalized set', function () {
    $rows = app(ParsePastedIngredients::class)->handle('2 handfuls spinach');

    expect($rows[0])->toBe([
        'qty' => 2.0,
        'unit' => null,
        'name' => 'handfuls spinach',
        'note' => null,
    ]);
});

it('parses a qty with no unit', function () {
    $rows = app(ParsePastedIngredients::class)->handle('3 eggs');

    expect($rows[0])->toBe([
        'qty' => 3.0,
        'unit' => null,
        'name' => 'eggs',
        'note' => null,
    ]);
});

it('skips blank lines and strips list bullets', function () {
    $rows = app(ParsePastedIngredients::class)->handle("- 2 tbsp olive oil\n\n* 100 g feta\n");

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('olive oil')
        ->and($rows[0]['unit'])->toBe('tbsp')
        ->and($rows[1]['qty'])->toBe(100.0)
        ->and($rows[1]['unit'])->toBe('g')
        ->and($rows[1]['name'])->toBe('feta');
});

it('lowercases ingredient names', function () {
    $rows = app(ParsePastedIngredients::class)->handle('2 cups Basmati Rice');

    expect($rows[0]['name'])->toBe('basmati rice');
});

it('reads a quantity glued to its unit', function () {
    $rows = app(ParsePastedIngredients::class)->handle("200g tomato\n2kg shredded meat");

    expect($rows[0])->toBe(['qty' => 200.0, 'unit' => 'g', 'name' => 'tomato', 'note' => null])
        ->and($rows[1])->toBe(['qty' => 2.0, 'unit' => 'kg', 'name' => 'meat', 'note' => 'shredded']);
});

it('moves a wordy amount into the note', function () {
    $rows = app(ParsePastedIngredients::class)->handle("as required vegetable oil\nto taste salt\npinch of salt\nhandful coriander\nto serve pita bread");

    expect(array_column($rows, 'name'))->toBe(['vegetable oil', 'salt', 'salt', 'coriander', 'pita bread'])
        ->and(array_column($rows, 'note'))->toBe(['as required', 'to taste', 'pinch of', 'handful', 'to serve']);
});

it('keeps a size and its piece word as the note rather than a quantity', function () {
    $rows = app(ParsePastedIngredients::class)->handle("2cm piece ginger\n1 inch knob ginger");

    expect($rows[0])->toBe(['qty' => null, 'unit' => null, 'name' => 'ginger', 'note' => '2cm piece'])
        ->and($rows[1])->toBe(['qty' => null, 'unit' => null, 'name' => 'ginger', 'note' => '1 inch knob']);
});

it('joins a wordy amount with a comma note', function () {
    $rows = app(ParsePastedIngredients::class)->handle('pinch of salt, or more');

    expect($rows[0]['name'])->toBe('salt')
        ->and($rows[0]['note'])->toBe('pinch of, or more');
});
