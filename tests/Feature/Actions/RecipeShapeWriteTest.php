<?php

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\UpdateRecipe;
use App\Enums\RecipeSource;
use App\Exceptions\RecipeShapeRefused;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

function shapeAttributes(array $overrides = []): array
{
    return array_merge([
        'title' => 'Hibachi chicken',
        'description' => 'Flat-top dinner.',
        'source' => RecipeSource::Manual,
        'source_url' => 'https://example.com/hibachi',
        'meal_type' => 'dinner',
        'prep_minutes' => 10,
        'cook_minutes' => 15,
        'servings' => 4,
        'tags' => [],
    ], $overrides);
}

function shapeIngredients(): array
{
    return [
        ['name' => 'onion', 'qty' => 1, 'unit' => 'count', 'prep_note' => 'diced'],
        ['name' => 'salt', 'qty' => 1, 'unit' => 'tsp'],
    ];
}

it('saves tools, alternatives, counts, prep notes and steps in order', function () {
    $recipe = app(CreateRecipe::class)->handle(
        shapeAttributes(),
        shapeIngredients(),
        [
            ['alternatives' => ['flat-top griddle', 'large skillet'], 'count' => 1],
            ['alternatives' => ['sheet pan'], 'count' => 2],
        ],
        ['Prep the onion.', 'Sear the chicken.', 'Serve.'],
    );

    $tools = $recipe->recipeTools()->with('alternatives')->get();

    expect($tools->pluck('position')->all())->toBe([1, 2])
        ->and($tools[0]->alternatives->pluck('word')->all())->toBe(['flat-top griddle', 'large skillet'])
        ->and($tools[0]->alternatives->pluck('position')->all())->toBe([1, 2])
        ->and($tools[1]->count)->toBe(2)
        ->and($recipe->cookingSteps->pluck('text')->all())->toBe(['Prep the onion.', 'Sear the chicken.', 'Serve.'])
        ->and($recipe->ingredients->firstWhere('name', 'onion')->pivot->note)->toBe('diced')
        ->and($recipe->ingredients->firstWhere('name', 'salt')->pivot->note)->toBeNull()
        ->and($recipe->hasShape())->toBeTrue();
});

it('resolves a word by kind name and by other name, and keeps an unknown word with no kind', function () {
    $recipe = app(CreateRecipe::class)->handle(
        shapeAttributes(),
        shapeIngredients(),
        [['alternatives' => ['Skillet', 'frying pan', 'mystery gadget']]],
        ['Cook.'],
    );

    $alternatives = $recipe->recipeTools()->first()->alternatives;

    expect($alternatives[0]->kind->name)->toBe('skillet')
        ->and($alternatives[1]->kind->name)->toBe('skillet')
        ->and($alternatives[2]->word)->toBe('mystery gadget')
        ->and($alternatives[2]->kind)->toBeNull()
        ->and($recipe->recipeTools()->first()->count)->toBe(1);
});

it('refuses a shape with an empty part and saves nothing', function (array $tools, array $ingredients, array $steps) {
    expect(fn () => app(CreateRecipe::class)->handle(shapeAttributes(), $ingredients, $tools, $steps))
        ->toThrow(RecipeShapeRefused::class);

    expect(Recipe::count())->toBe(0);
})->with([
    'no tools' => [[], shapeIngredients(), ['Cook.']],
    'tool with no words' => [[['alternatives' => ['  ']]], shapeIngredients(), ['Cook.']],
    'no ingredients' => [[['alternatives' => ['wok']]], [], ['Cook.']],
    'no steps' => [[['alternatives' => ['wok']]], shapeIngredients(), []],
    'blank steps' => [[['alternatives' => ['wok']]], shapeIngredients(), [' ', '']],
]);

it('replaces the shape on update', function () {
    $recipe = app(CreateRecipe::class)->handle(
        shapeAttributes(),
        shapeIngredients(),
        [['alternatives' => ['wok']]],
        ['Old step.'],
    );

    app(UpdateRecipe::class)->handle(
        $recipe,
        ['title' => 'Renamed again'],
        [['name' => 'rice', 'prep_note' => 'rinsed']],
        [['alternatives' => ['rice cooker', 'pots'], 'count' => 1]],
        ['New one.', 'New two.'],
    );

    $recipe->refresh();

    expect($recipe->recipeTools()->count())->toBe(1)
        ->and($recipe->recipeTools()->first()->alternatives->pluck('word')->all())->toBe(['rice cooker', 'pots'])
        ->and($recipe->cookingSteps->pluck('text')->all())->toBe(['New one.', 'New two.'])
        ->and($recipe->ingredients->first()->pivot->note)->toBe('rinsed')
        ->and(DB::table('recipe_tools')->count())->toBe(1);
});

it('refuses an update that supplies the shape with an empty part and leaves the recipe alone', function () {
    $recipe = app(CreateRecipe::class)->handle(
        shapeAttributes(),
        shapeIngredients(),
        [['alternatives' => ['wok']]],
        ['Step.'],
    );

    expect(fn () => app(UpdateRecipe::class)->handle($recipe, ['title' => 'Changed'], shapeIngredients(), [], ['Step.']))
        ->toThrow(RecipeShapeRefused::class);

    expect($recipe->fresh()->title)->toBe('Hibachi chicken')
        ->and($recipe->recipeTools()->count())->toBe(1);
});
