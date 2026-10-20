<?php

use App\Actions\Recipes\RestructureIngredientLine;

it('splits an ingredient line into amount, unit, name and prep note without inventing anything', function (string $line, ?float $qty, ?string $unit, string $name, ?string $prepNote) {
    expect(app(RestructureIngredientLine::class)->handle($line))->toBe([
        'qty' => $qty,
        'unit' => $unit,
        'name' => $name,
        'prep_note' => $prepNote,
    ]);
})->with([
    'text after the first comma is the prep note' => ['2 cloves garlic, minced', 2.0, null, 'cloves garlic', 'minced'],
    'a can stays in the name, no weight invented' => ['1 can tomatoes', 1.0, null, 'can tomatoes', null],
    'to taste is a prep note, amount stays blank' => ['salt to taste', null, null, 'salt', 'to taste'],
    'a unit word equal to an allowed unit' => ['2 tablespoons extra-virgin olive oil', 2.0, 'tbsp', 'extra-virgin olive oil', null],
    'extra spaces and a trailing bracket note' => ['1  medium yellow onion (chopped)', 1.0, null, 'medium yellow onion', 'chopped'],
    'a nested bracket note keeps all its text' => ['4  medium carrots (chopped into small pieces (2½ cups))', 4.0, null, 'medium carrots', 'chopped into small pieces (2½ cups)'],
    'a mixed unicode fraction' => ['1¼ teaspoons sea salt', 1.25, 'tsp', 'sea salt', null],
    'a lone unicode fraction' => ['¼ teaspoon freshly ground black pepper (plus more to taste)', 0.25, 'tsp', 'freshly ground black pepper', 'plus more to taste'],
    'a unit the allowed set lacks stays in the name' => ['1  bunch fresh parsley (stems bundled, leaves chopped and reserved for garnish)', 1.0, null, 'bunch fresh parsley', 'stems bundled, leaves chopped and reserved for garnish'],
    'ounces map to oz' => ['8 ounces pastina (about 1 cup)', 8.0, 'oz', 'pastina', 'about 1 cup'],
    'cups map to cup' => ['8 cups vegetable broth', 8.0, 'cup', 'vegetable broth', null],
    'a line with no amount' => ['fresh basil, torn', null, null, 'fresh basil', 'torn'],
    'a slash fraction after a whole number' => ['1 1/2 cups flour', 1.5, 'cup', 'flour', null],
    'a bracketed exact amount after the container' => ['1 can (400 g) tomatoes', 400.0, 'g', 'tomatoes', '1 can'],
    'a bracketed exact amount before the container' => ['1 (14-ounce) can tomatoes', 14.0, 'oz', 'tomatoes', '1 can'],
    'a bracketed amount with a space and a spelled-out unit' => ['1 jar (12 ounces) roasted red peppers, drained', 12.0, 'oz', 'roasted red peppers', '1 jar, drained'],
    'several containers keep the count and leave the bracket where the page put it' => ['2 cans (400 g) tomatoes', 2.0, null, 'cans (400 g) tomatoes', null],
    'a bracket that is not an amount and unit stays put' => ['1 can (about this much) tomatoes', 1.0, null, 'can (about this much) tomatoes', null],
]);
