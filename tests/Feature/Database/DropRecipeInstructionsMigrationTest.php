<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function dropRecipeInstructionsMigration(): object
{
    return require database_path('migrations/2026_10_09_200000_drop_recipe_instructions.php');
}

function migrationRefusal(): string
{
    try {
        dropRecipeInstructionsMigration()->up();
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    return '';
}

beforeEach(function () {
    // The suite's database is already migrated, so the column is gone; restore
    // it the way a rollback would, then run the migration under test.
    dropRecipeInstructionsMigration()->down();
});

it('refuses while a recipe lacks the shape, naming it by id and title', function () {
    Recipe::factory()->create(['title' => 'Fine Soup']);
    $bare = Recipe::factory()->unshaped()->create(['title' => 'Bare Toast']);
    $noSteps = Recipe::factory()->create(['title' => 'Stepless Stew']);
    $noSteps->cookingSteps()->delete();

    expect(fn () => dropRecipeInstructionsMigration()->up())
        ->toThrow(RuntimeException::class, "#{$bare->id} Bare Toast");

    expect(migrationRefusal())->toContain("#{$noSteps->id} Stepless Stew")
        ->not->toContain('Fine Soup')
        ->and(Schema::hasColumn('recipes', 'instructions'))->toBeTrue();
});

it('names a recipe that lacks only a tool or only an ingredient', function () {
    $noTool = Recipe::factory()->create(['title' => 'Toolless']);
    $noTool->recipeTools()->each(fn ($tool) => $tool->delete());
    $noIngredient = Recipe::factory()->create(['title' => 'Ingredientless']);
    $noIngredient->ingredients()->detach();

    expect(migrationRefusal())
        ->toContain("#{$noTool->id} Toolless")
        ->toContain("#{$noIngredient->id} Ingredientless");
});

it('drops the column once every recipe has the shape', function () {
    Recipe::factory()->count(2)->create();
    $late = Recipe::factory()->unshaped()->create(['title' => 'Late Fixer']);

    expect(fn () => dropRecipeInstructionsMigration()->up())->toThrow(RuntimeException::class);

    $late->recipeTools()->create(['position' => 1, 'count' => 1])->alternatives()->create(['position' => 1, 'word' => 'wok']);
    $late->ingredients()->attach(Ingredient::factory()->create()->id, ['qty' => 1, 'unit' => 'count', 'note' => null]);
    $late->cookingSteps()->create(['position' => 1, 'text' => 'Cook.']);

    dropRecipeInstructionsMigration()->up();

    expect(Schema::hasColumn('recipes', 'instructions'))->toBeFalse()
        ->and(Recipe::count())->toBe(3);
});

it('drops the column when there are no recipes at all', function () {
    dropRecipeInstructionsMigration()->up();

    expect(Schema::hasColumn('recipes', 'instructions'))->toBeFalse();
});

it('restores a nullable column when rolled back', function () {
    dropRecipeInstructionsMigration()->up();
    dropRecipeInstructionsMigration()->down();

    $recipe = Recipe::factory()->create();

    expect(Schema::hasColumn('recipes', 'instructions'))->toBeTrue()
        ->and(DB::table('recipes')->where('id', $recipe->id)->value('instructions'))->toBeNull();
});
