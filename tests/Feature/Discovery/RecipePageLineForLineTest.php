<?php

use App\Actions\Recipes\RestructureIngredientLine;
use App\Discovery\RecipePageReader;
use App\Enums\RecipePageRefusal;

/** A page whose one recipe has the given ingredient lines. */
function pageWithLines(array $lines): string
{
    return '<script type="application/ld+json">'.json_encode([
        '@type' => 'Recipe', 'name' => 'X', 'recipeIngredient' => $lines,
        'recipeInstructions' => ['Cook it in a pan.'], 'prepTime' => 'PT5M', 'cookTime' => 'PT5M', 'recipeYield' => '4',
    ]).'</script>';
}

/** Make the restructuring answer with the given row, whatever the line. */
function restructuringTo(array $row): void
{
    app()->bind(RestructureIngredientLine::class, fn () => new class($row) extends RestructureIngredientLine
    {
        public function __construct(private array $row) {}

        public function handle(string $line): array
        {
            return $this->row;
        }
    });
}

it('refuses a row that drops a word of the source line', function (string $line, array $row) {
    restructuringTo($row);

    $reading = app(RecipePageReader::class)->read(pageWithLines([$line]), 'https://example.com/x');

    expect($reading->candidate)->toBeNull()
        ->and($reading->refusal)->toBe(RecipePageRefusal::NotLineForLine);
})->with([
    'a prep note left out' => ['2 tablespoons olive oil, divided', ['qty' => 2.0, 'unit' => 'tbsp', 'name' => 'olive oil', 'prep_note' => null]],
    'an adjective left out' => ['1 large red onion', ['qty' => 1.0, 'unit' => null, 'name' => 'onion', 'prep_note' => null]],
    'a unit that is not the source unit' => ['2 tablespoons butter', ['qty' => 2.0, 'unit' => 'tsp', 'name' => 'butter', 'prep_note' => null]],
    'a unit left out' => ['2 tablespoons butter', ['qty' => 2.0, 'unit' => null, 'name' => 'butter', 'prep_note' => null]],
    'an amount left out' => ['2 eggs', ['qty' => null, 'unit' => null, 'name' => 'eggs', 'prep_note' => null]],
    'an amount changed' => ['2 eggs', ['qty' => 3.0, 'unit' => null, 'name' => 'eggs', 'prep_note' => null]],
    'a fraction changed' => ['½ cup milk', ['qty' => 0.25, 'unit' => 'cup', 'name' => 'milk', 'prep_note' => null]],
]);

it('accepts a row that carries every word of the source line, through the unit alias table and normalized fractions', function (string $line, array $row) {
    restructuringTo($row);

    $reading = app(RecipePageReader::class)->read(pageWithLines([$line]), 'https://example.com/x');

    expect($reading->refusal)->toBeNull();
})->with([
    'tablespoons is tbsp' => ['2 tablespoons olive oil', ['qty' => 2.0, 'unit' => 'tbsp', 'name' => 'olive oil', 'prep_note' => null]],
    'a unicode fraction is its decimal' => ['1½ cups flour', ['qty' => 1.5, 'unit' => 'cup', 'name' => 'flour', 'prep_note' => null]],
    'a slash fraction is its decimal' => ['1 1/4 cups flour', ['qty' => 1.25, 'unit' => 'cup', 'name' => 'flour', 'prep_note' => null]],
    'a words order and case do not matter' => ['Salt, to taste', ['qty' => null, 'unit' => null, 'name' => 'salt', 'prep_note' => 'to taste']],
    'a bracketed exact amount with the container in the prep note' => ['1 can (400 g) tomatoes', ['qty' => 400.0, 'unit' => 'g', 'name' => 'tomatoes', 'prep_note' => '1 can']],
    'the joining of is not a word that needs a place' => ['1 cup of rice', ['qty' => 1.0, 'unit' => 'cup', 'name' => 'rice', 'prep_note' => null]],
]);

it('gives the exact amount and allowed unit a page states in brackets, with the container word kept', function () {
    $reading = app(RecipePageReader::class)->read(pageWithLines(['1 (14-ounce) can tomatoes', '1 can (400 g) chickpeas, drained']), 'https://example.com/x');

    expect($reading->refusal)->toBeNull()
        ->and($reading->candidate['ingredients'])->toBe([
            ['qty' => 14.0, 'unit' => 'oz', 'name' => 'tomatoes', 'prep_note' => '1 can'],
            ['qty' => 400.0, 'unit' => 'g', 'name' => 'chickpeas', 'prep_note' => '1 can, drained'],
        ]);
});
