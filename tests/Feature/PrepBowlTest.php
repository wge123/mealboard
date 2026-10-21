<?php

use App\Actions\Recipes\SyncRecipeIngredients;
use App\Discovery\RecipeOutputFormat;
use App\Livewire\RecipeDetail;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

function prepBowlRecipe(): Recipe
{
    $recipe = Recipe::factory()->unshaped()->approved()->create(['title' => 'Chana Masala']);
    $recipe->recipeTools()->create(['position' => 1, 'count' => 1])->alternatives()->create(['position' => 1, 'word' => 'pot']);
    $recipe->cookingSteps()->create(['position' => 1, 'text' => 'Fry the aromatics, then the spices.']);

    foreach ([
        ['onion', 'diced', 'Aromatics'],
        ['cumin', null, 'spice bowl'],
        ['chickpeas', 'drained', null],
        ['garlic', 'minced', 'aromatics'],
        ['coriander', null, 'spice bowl'],
    ] as [$name, $note, $bowl]) {
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => $name])->id, ['qty' => 1, 'unit' => 'count', 'note' => $note, 'prep_bowl' => $bowl]);
    }

    return $recipe;
}

it('stores a prep bowl per ingredient line, trimmed, and none for a blank one', function () {
    $recipe = Recipe::factory()->create();

    app(SyncRecipeIngredients::class)->handle($recipe, [
        ['name' => 'cumin', 'prep_bowl' => '  spice bowl '],
        ['name' => 'onion', 'prep_note' => 'diced', 'prep_bowl' => ''],
        ['name' => 'salt'],
    ]);

    $bowls = $recipe->ingredients()->get()->mapWithKeys(fn ($ingredient) => [$ingredient->name => $ingredient->pivot->prep_bowl]);

    expect($bowls->all())->toBe(['cumin' => 'spice bowl', 'onion' => null, 'salt' => null]);
});

it('asks a model for a prep bowl only where the source combines ingredients', function () {
    expect(RecipeOutputFormat::schema('null'))->toContain('"prep_bowl": string or null')
        ->toContain('never invent a bowl');
});

it('gathers each prep bowl under its name in the mise en place, where its first ingredient sits', function () {
    $recipe = prepBowlRecipe();

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSee('data-prep-bowl="Aromatics"', false)
        ->assertSee('data-prep-bowl="spice bowl"', false)
        ->assertSeeInOrder(['Mise en place', 'Aromatics', 'onion', 'garlic', 'Spice bowl', 'cumin', 'coriander', 'chickpeas', 'Cooking']);
});

it('shows no prep bowl rows for a recipe without bowls', function () {
    $recipe = Recipe::factory()->approved()->create();

    $this->actingAs(User::factory()->create())->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertDontSee('data-prep-bowl', false);
});

it('edits an ingredient line\'s prep bowl in the recipe form', function () {
    $recipe = prepBowlRecipe();

    $form = Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing');

    expect(array_column($form->get('rows'), 'prep_bowl', 'name'))
        ->toBe(['onion' => 'Aromatics', 'cumin' => 'spice bowl', 'chickpeas' => '', 'garlic' => 'aromatics', 'coriander' => 'spice bowl']);

    $form->set('rows.2.prep_bowl', 'Beans')
        ->set('rows.0.prep_bowl', '')
        ->call('save')
        ->assertHasNoErrors();

    $bowls = $recipe->fresh()->ingredients->mapWithKeys(fn ($ingredient) => [$ingredient->name => $ingredient->pivot->prep_bowl]);

    expect($bowls['chickpeas'])->toBe('Beans')
        ->and($bowls['onion'])->toBeNull()
        ->and($bowls['cumin'])->toBe('spice bowl');
});
