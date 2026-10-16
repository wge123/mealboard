<?php

use App\Livewire\RecipeDetail;
use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

function cookModeRecipe(): Recipe
{
    $recipe = Recipe::factory()->unshaped()->approved()->create([
        'title' => 'Garlic Noodles', 'cuisine' => 'thai', 'prep_minutes' => 10, 'cook_minutes' => 15, 'servings' => 4,
    ]);
    $recipe->recipeTools()->create(['position' => 1, 'count' => 1])->alternatives()->create(['position' => 1, 'word' => 'wok']);
    $recipe->recipeTools()->create(['position' => 2, 'count' => 2])->alternatives()->create(['position' => 1, 'word' => 'tongs']);
    $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'noodles'])->id, ['qty' => 200, 'unit' => 'g', 'note' => null]);
    $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'garlic'])->id, ['qty' => 4, 'unit' => 'count', 'note' => 'minced']);
    $recipe->cookingSteps()->create(['position' => 1, 'text' => 'Boil the noodles.']);
    $recipe->cookingSteps()->create(['position' => 2, 'text' => 'Toss with garlic.']);

    return $recipe;
}

it('reads as one page: fact line, Mise en place with the tools to get out, Cooking, then Details', function () {
    $recipe = cookModeRecipe();

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSeeInOrder([
            'Garlic Noodles', '25 min', 'Serves 4',
            'Mise en place', 'Get out', 'wok', '2 ×', 'tongs', '200 g', 'noodles', 'garlic', 'minced',
            'Cooking', 'Boil the noodles.', 'Toss with garlic.',
            'Details', 'Cuisine', 'Thai',
        ]);
});

it('drops the old tabs and boxed sections', function () {
    $recipe = cookModeRecipe();

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertDontSee('data-section="tools"', false)
        ->assertDontSee('role="tablist"', false);
});

it('links Add to a week to the plan page', function () {
    $recipe = cookModeRecipe();

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSee('Add to a week')
        ->assertSee(route('plan.builder'), false);
});

it('flags a missing tool with a warning badge and counts it on the fact line', function () {
    KitchenToolKind::where('name', 'wok')->update(['owned' => false]);
    KitchenToolKind::where('name', 'tongs')->update(['owned' => true]);
    $recipe = cookModeRecipe();
    $recipe->recipeTools()->orderBy('position')->first()->alternatives()->first()->update(['kitchen_tool_kind_id' => KitchenToolKind::where('name', 'wok')->value('id')]);
    $recipe->recipeTools()->orderBy('position')->get()[1]->alternatives()->first()->update(['kitchen_tool_kind_id' => KitchenToolKind::where('name', 'tongs')->value('id')]);

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSee('data-missing-tool', false)
        ->assertSee('1 tool missing');
});

it('shows no missing-tool flag when every tool is owned', function () {
    KitchenToolKind::where('name', 'skillet')->update(['owned' => true]);
    $recipe = Recipe::factory()->approved()->create();

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertDontSee('data-missing-tool', false)
        ->assertDontSee('missing');
});

it('keeps approve, reject and archive reachable from the page', function () {
    $recipe = Recipe::factory()->create(['status' => 'pending']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->assertSee('Approve')->assertSee('Reject')->assertSee('Archive')->assertSee('Edit');
});
