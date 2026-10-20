<?php

use App\Discovery\CandidateValidator;
use App\Discovery\RecipePageReader;
use App\Enums\RecipePageRefusal;

function pastinaPage(): string
{
    return file_get_contents(base_path('tests/Fixtures/recipe-pages/loveandlemons-pastina-soup.html'));
}

const PASTINA_URL = 'https://www.loveandlemons.com/pastina-soup/';

it('reads the recipe from a Love and Lemons page whose type attribute is unquoted', function () {
    $reading = app(RecipePageReader::class)->read(pastinaPage(), PASTINA_URL);

    expect($reading->refusal)->toBeNull();

    $candidate = $reading->candidate;

    expect($candidate['title'])->toBe('Pastina Soup')
        ->and($candidate['source_url'])->toBe(PASTINA_URL)
        ->and($candidate['description'])->toStartWith('This pastina soup recipe is filled with tiny pasta')
        ->and($candidate['description'])->toContain("it's soothing") // entities decoded
        ->and($candidate['prep_minutes'])->toBe(20)
        ->and($candidate['cook_minutes'])->toBe(45)
        ->and($candidate['servings'])->toBe(6)
        ->and($candidate['meal_type'])->toBe('dinner')
        ->and($candidate['cuisine'])->toBe('american')
        ->and($candidate['ingredients'])->toHaveCount(14)
        ->and($candidate['steps'])->toHaveCount(5)
        ->and($candidate['steps'][0])->toStartWith('Heat the olive oil in a large pot or Dutch oven')
        ->and($candidate['steps'][4])->toBe('Serve topped with freshly grated Parmesan and chopped fresh parsley leaves.');
});

it('restructures every ingredient line of the page', function () {
    $ingredients = app(RecipePageReader::class)->read(pastinaPage(), PASTINA_URL)->candidate['ingredients'];

    expect($ingredients[0])->toBe(['qty' => 2.0, 'unit' => 'tbsp', 'name' => 'extra-virgin olive oil', 'prep_note' => null])
        ->and($ingredients[1])->toBe(['qty' => 1.0, 'unit' => null, 'name' => 'medium yellow onion', 'prep_note' => 'chopped'])
        ->and($ingredients[4])->toBe(['qty' => 1.25, 'unit' => 'tsp', 'name' => 'sea salt', 'prep_note' => null])
        ->and($ingredients[12])->toBe(['qty' => 8.0, 'unit' => 'oz', 'name' => 'pastina', 'prep_note' => 'about 1 cup']);
});

it('finds the kitchen tools the steps name by a known kind word or other name', function () {
    $tools = app(RecipePageReader::class)->read(pastinaPage(), PASTINA_URL)->candidate['tools'];

    expect($tools)->toContain(['alternatives' => ['pot', 'dutch oven'], 'count' => 1])
        ->and($tools)->toContain(['alternatives' => ['blender'], 'count' => 1])
        ->and(collect($tools)->pluck('alternatives')->flatten()->all())->not->toContain('oven', 'slotted spoon');
});

it('gives a candidate the shared candidate check passes', function () {
    $candidate = app(RecipePageReader::class)->read(pastinaPage(), PASTINA_URL)->candidate;

    expect(app(CandidateValidator::class)->check($candidate)->passes())->toBeTrue();
});

it('refuses a page with no recipe data, no ingredients, or no steps by name', function (string $html, RecipePageRefusal $refusal) {
    $reading = app(RecipePageReader::class)->read($html, PASTINA_URL);

    expect($reading->candidate)->toBeNull()
        ->and($reading->refusal)->toBe($refusal);
})->with(function () {
    $page = fn (array $recipe) => '<html><script type="application/ld+json">'.json_encode(['@context' => 'https://schema.org', '@type' => 'Recipe', 'name' => 'X'] + $recipe).'</script></html>';

    return [
        'no script block' => ['<html><body>Just an article</body></html>', RecipePageRefusal::NoRecipeData],
        'no Recipe node' => ['<html><script type="application/ld+json">{"@type":"Article","name":"x"}</script></html>', RecipePageRefusal::NoRecipeData],
        'no ingredients' => [$page(['recipeInstructions' => ['Cook it.']]), RecipePageRefusal::NoIngredients],
        'no steps' => [$page(['recipeIngredient' => ['1 egg']]), RecipePageRefusal::NoSteps],
    ];
});
