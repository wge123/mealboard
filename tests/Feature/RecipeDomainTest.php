<?php

use App\Enums\IngredientCategory;
use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a recipe via factory with working enum casts', function () {
    $recipe = Recipe::factory()->create([
        'source' => 'discovered',
        'status' => 'pending',
        'meal_type' => 'dinner',
        'tags' => ['quick', 'healthy'],
    ]);

    $recipe->refresh();

    expect($recipe->source)->toBe(RecipeSource::Discovered)
        ->and($recipe->status)->toBe(RecipeStatus::Pending)
        ->and($recipe->meal_type)->toBe(MealType::Dinner)
        ->and($recipe->tags)->toBe(['quick', 'healthy']);
});

it('sets approved metadata via the approved factory state', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->approved($user)->create();

    expect($recipe->status)->toBe(RecipeStatus::Approved)
        ->and($recipe->approved_at)->not->toBeNull()
        ->and($recipe->approvedBy->is($user))->toBeTrue();
});

it('creates an ingredient via factory with working enum casts', function () {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Chicken Thighs',
        'category' => 'meat',
    ]);

    $ingredient->refresh();

    expect($ingredient->category)->toBe(IngredientCategory::Meat)
        ->and($ingredient->name)->toBe('chicken thighs')
        ->and($ingredient->is_pantry_staple)->toBeFalse();
});

it('rejects duplicate ingredient names', function () {
    Ingredient::factory()->create(['name' => 'salt']);

    expect(fn () => Ingredient::factory()->create(['name' => 'Salt']))
        ->toThrow(QueryException::class);
});

it('attaches ingredients to a recipe with qty, unit and note pivot data', function () {
    $recipe = Recipe::factory()->create();
    $flour = Ingredient::factory()->create(['name' => 'flour']);
    $milk = Ingredient::factory()->create(['name' => 'milk']);

    $recipe->ingredients()->attach([
        $flour->id => ['qty' => 250, 'unit' => Unit::Gram->value, 'note' => 'sifted'],
        $milk->id => ['qty' => 1.5, 'unit' => Unit::Cup->value, 'note' => null],
    ]);

    $recipe->refresh();

    expect($recipe->ingredients)->toHaveCount(2);

    $pivot = $recipe->ingredients->firstWhere('name', 'flour')->pivot;
    expect((float) $pivot->qty)->toBe(250.0)
        ->and($pivot->unit)->toBe('g')
        ->and($pivot->note)->toBe('sifted');

    $milkPivot = $recipe->ingredients->firstWhere('name', 'milk')->pivot;
    expect((float) $milkPivot->qty)->toBe(1.5)
        ->and($milkPivot->unit)->toBe('cup')
        ->and($milkPivot->note)->toBeNull();
});
