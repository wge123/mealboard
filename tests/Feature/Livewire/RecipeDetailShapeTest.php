<?php

use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Models\User;

it('shows the tools to get out, the ingredients and the steps in order for a shaped recipe', function () {
    $recipe = Recipe::factory()->unshaped()->approved()->create();
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
            'Mise en place', 'Get out', '2 ×', 'flat-top griddle or large skillet', 'tongs',
            'scallion', 'whites and greens apart', 'salt',
            'Cooking', 'Heat the griddle.', 'Sear everything.',
        ]);
});

it('builds a shaped recipe from the factory state', function () {
    $recipe = Recipe::factory()->create();

    expect($recipe->hasShape())->toBeTrue()
        ->and($recipe->recipeTools()->first()->alternatives->first()->kind->name)->toBe('skillet');
});

it('marks a missing tool in the Get out list and leaves owned ones unmarked', function () {
    KitchenToolKind::where('name', 'wok')->update(['owned' => false]);
    KitchenToolKind::where('name', 'skillet')->update(['owned' => true]);
    $recipe = Recipe::factory()->approved()->create();
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

it('badges a pantry staple in the mise en place and leaves other ingredients bare', function () {
    $recipe = Recipe::factory()->approved()->create();
    $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'soy sauce', 'is_pantry_staple' => true])->id, ['qty' => 1, 'unit' => 'tbsp', 'note' => null]);
    $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'salmon', 'is_pantry_staple' => false])->id, ['qty' => 2, 'unit' => 'count', 'note' => null]);

    $html = $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")->assertOk()->getContent();

    $badge = preg_match_all('/>\s*staple\s*</', $html, $found, PREG_OFFSET_CAPTURE);

    expect($badge)->toBe(1)
        ->and(strpos($html, 'soy sauce'))->toBeLessThan($found[0][0][1])
        ->and($found[0][0][1])->toBeLessThan(strpos($html, 'salmon'));
});
