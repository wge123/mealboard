<?php

use App\Actions\Recipes\RestructureIngredientLine;
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
        'empty ingredients' => [$page(['recipeIngredient' => [], 'recipeInstructions' => ['Cook it.']]), RecipePageRefusal::NoIngredients],
        'blank ingredient lines' => [$page(['recipeIngredient' => ['', '  '], 'recipeInstructions' => ['Cook it.']]), RecipePageRefusal::NoIngredients],
        'empty steps' => [$page(['recipeIngredient' => ['1 egg'], 'recipeInstructions' => []]), RecipePageRefusal::NoSteps],
        'steps that are only an empty section' => [$page(['recipeIngredient' => ['1 egg'], 'recipeInstructions' => [['@type' => 'HowToSection', 'name' => 'Cook', 'itemListElement' => []]]]), RecipePageRefusal::NoSteps],
    ];
});

/** A saved research capture of a page's Recipe JSON-LD block, put back inside a page. */
function capturedPage(string $capture): string
{
    return '<html><head><script type="application/ld+json">'.file_get_contents(base_path("tests/Fixtures/recipe-pages/{$capture}.json")).'</script></head><body></body></html>';
}

it('reads a page whose JSON-LD is a list of nodes (Allrecipes)', function () {
    $candidate = app(RecipePageReader::class)->read(capturedPage('allrecipes-list'), 'https://www.allrecipes.com/x')->candidate;

    expect($candidate['title'])->toBe('Balsamic Roasted Brussels Sprouts with Bacon')
        ->and($candidate['ingredients'])->toHaveCount(7)
        ->and($candidate['steps'])->toHaveCount(5)
        ->and($candidate['prep_minutes'])->toBe(15)
        ->and($candidate['cook_minutes'])->toBe(15)
        ->and($candidate['servings'])->toBe(4);
});

it('reads a page whose JSON-LD is one top-level object (BBC Good Food)', function () {
    $candidate = app(RecipePageReader::class)->read(capturedPage('bbcgoodfood-object'), 'https://www.bbcgoodfood.com/x')->candidate;

    expect($candidate['title'])->toBe('Leek & potato soup (Vichyssoise)')
        ->and($candidate['ingredients'])->toHaveCount(10)
        ->and($candidate['steps'])->toHaveCount(8)
        ->and($candidate['servings'])->toBe(8);
});

it('reads a Recipe whose type is an array that also names another type (Simply Recipes)', function () {
    $candidate = app(RecipePageReader::class)->read(capturedPage('simplyrecipes-type-array'), 'https://www.simplyrecipes.com/x')->candidate;

    expect($candidate['ingredients'])->toHaveCount(14)
        ->and($candidate['steps'])->toHaveCount(3)
        ->and($candidate['steps'][0])->toStartWith('In a large skillet, heat olive oil');
});

it('flattens HowToSection groups in order and drops their headings (Budget Bytes, Just One Cookbook)', function (string $capture, int $steps, array $headings) {
    $candidate = app(RecipePageReader::class)->read(capturedPage($capture), 'https://example.com/x')->candidate;

    expect($candidate['steps'])->toHaveCount($steps);

    foreach ($headings as $heading) {
        expect(collect($candidate['steps'])->contains($heading))->toBeFalse();
    }
})->with([
    'budgetbytes' => ['budgetbytes-graph-sections', 13, ['For the Meatballs:', 'For the Soup:', 'For the Meatballs', 'For the Soup']],
    'justonecookbook' => ['justonecookbook-sections-entities', 8, ['To Prepare the Cabbage', 'To Serve', 'To Store', 'To Freeze']],
]);

it('decodes HTML entities in every field it reads', function () {
    $candidate = app(RecipePageReader::class)->read(capturedPage('justonecookbook-sections-entities'), 'https://www.justonecookbook.com/x')->candidate;

    expect(json_encode($candidate, JSON_UNESCAPED_UNICODE))->not->toMatch('/&(amp|quot|#\d+|#x[0-9a-f]+|[a-z]+);/i');
});

it('reads plain-string steps (King Arthur)', function () {
    $candidate = app(RecipePageReader::class)->read(capturedPage('kingarthur-string-steps'), 'https://www.kingarthurbaking.com/x')->candidate;

    expect($candidate['steps'])->toHaveCount(10)
        ->and($candidate['steps'][0])->toStartWith('Weigh your flours')
        ->and($candidate['prep_minutes'])->toBe(30)
        ->and($candidate['cook_minutes'])->toBe(7);
});

it('splits instructions given as one string into a step per line', function () {
    $page = '<script type="application/ld+json">'.json_encode([
        '@type' => 'Recipe', 'name' => 'X', 'recipeIngredient' => ['1 egg'],
        'recipeInstructions' => "Crack the egg.\nFry the egg.\n\nServe the egg.",
    ]).'</script>';

    expect(app(RecipePageReader::class)->read($page, PASTINA_URL)->candidate['steps'])
        ->toBe(['Crack the egg.', 'Fry the egg.', 'Serve the egg.']);
});

it('reads a total time written as plain text and not split into prep and cook (Bon Appetit)', function () {
    $candidate = app(RecipePageReader::class)->read(capturedPage('bonappetit-plain-times'), 'https://www.bonappetit.com/x')->candidate;

    expect($candidate['prep_minutes'])->toBe(0)
        ->and($candidate['cook_minutes'])->toBe(50)
        ->and($candidate['servings'])->toBe(4)
        ->and($candidate['ingredients'])->toHaveCount(11);
});

it('refuses a restructured ingredient line that adds words the source line does not have', function () {
    app()->bind(RestructureIngredientLine::class, fn () => new class extends RestructureIngredientLine
    {
        public function handle(string $line): array
        {
            return ['qty' => 1.0, 'unit' => null, 'name' => 'black truffle', 'prep_note' => null];
        }
    });

    $reading = app(RecipePageReader::class)->read(capturedPage('bbcgoodfood-object'), 'https://www.bbcgoodfood.com/x');

    expect($reading->candidate)->toBeNull()
        ->and($reading->refusal?->value)->toBe('not-line-for-line');
});

it('passes the line-for-line check on every captured shape', function (string $capture) {
    $reading = app(RecipePageReader::class)->read(capturedPage($capture), 'https://example.com/x');

    expect($reading->refusal)->toBeNull();
})->with(['allrecipes-list', 'bbcgoodfood-object', 'budgetbytes-graph-sections', 'justonecookbook-sections-entities', 'kingarthur-string-steps', 'simplyrecipes-type-array', 'bonappetit-plain-times']);
