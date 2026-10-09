<?php

use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Models\User;

it('shows Tools, Mise en place and Cooking in order for a shaped recipe', function () {
    $recipe = Recipe::factory()->approved()->create(['instructions' => null]);
    $tool = $recipe->recipeTools()->create(['position' => 1, 'count' => 2]);
    $tool->alternatives()->create(['position' => 1, 'word' => 'flat-top griddle']);
    $tool->alternatives()->create(['position' => 2, 'word' => 'large skillet']);
    $recipe->recipeTools()->create(['position' => 2, 'count' => 1])
        ->alternatives()->create(['position' => 1, 'word' => 'tongs']);
    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'scallion'])->id,
        ['qty' => 3, 'unit' => 'count', 'note' => 'whites and greens apart'],
    );
    $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'salt'])->id, ['qty' => null, 'unit' => null, 'note' => null]);
    $recipe->cookingSteps()->create(['position' => 1, 'text' => 'Heat the griddle.']);
    $recipe->cookingSteps()->create(['position' => 2, 'text' => 'Sear everything.']);

    $this->actingAs(User::factory()->create())
        ->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSeeInOrder([
            'Tools', '2 ×', 'flat-top griddle or large skillet', 'tongs',
            'Mise en place', 'scallion', 'whites and greens apart', 'salt',
            'Cooking', 'Heat the griddle.', 'Sear everything.',
        ])
        ->assertDontSee('Instructions');
});

it('keeps showing the old method for a recipe without the shape', function () {
    $recipe = Recipe::factory()->approved()->create(['instructions' => '1. Boil water.']);

    $this->actingAs(User::factory()->create())
        ->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSee('Instructions')
        ->assertSee('Boil water.')
        ->assertDontSee('Mise en place');
});

it('builds a shaped recipe from the factory state', function () {
    $recipe = Recipe::factory()->shaped()->create();

    expect($recipe->hasShape())->toBeTrue()
        ->and($recipe->recipeTools()->first()->alternatives->first()->kind->name)->toBe('skillet');
});

it('marks a missing tool in the Tools list and leaves owned ones unmarked', function () {
    KitchenToolKind::where('name', 'wok')->update(['owned' => false]);
    KitchenToolKind::where('name', 'skillet')->update(['owned' => true]);
    $recipe = Recipe::factory()->approved()->shaped()->create();
    $recipe->recipeTools()->create(['position' => 2, 'count' => 1])->alternatives()->create([
        'position' => 1,
        'word' => 'wok',
        'kitchen_tool_kind_id' => KitchenToolKind::where('name', 'wok')->value('id'),
    ]);

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSeeInOrder(['skillet', 'wok', 'missing'])
        ->assertSee('data-missing-tool', false);
});
