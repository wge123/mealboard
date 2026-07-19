<?php

use App\Support\CleanIngredientKeywords;

it('strips quantity, unit, and prep note', function () {
    expect(app(CleanIngredientKeywords::class)->handle('2 cups diced yellow onion'))
        ->toBe('yellow onion');
});

it('strips a unit without a quantity', function () {
    expect(app(CleanIngredientKeywords::class)->handle('cup shredded cheddar'))
        ->toBe('cheddar');
});

it('strips prep words without quantity or unit', function () {
    expect(app(CleanIngredientKeywords::class)->handle('minced garlic'))
        ->toBe('garlic');
});

it('passes a plain name through lowercased', function () {
    expect(app(CleanIngredientKeywords::class)->handle('Chicken Thighs'))
        ->toBe('chicken thighs');
});

it('drops trailing comma notes', function () {
    expect(app(CleanIngredientKeywords::class)->handle('1 lb chicken thighs, trimmed'))
        ->toBe('chicken thighs');
});

it('returns an empty string for an empty line', function () {
    expect(app(CleanIngredientKeywords::class)->handle('  '))->toBe('');
});
