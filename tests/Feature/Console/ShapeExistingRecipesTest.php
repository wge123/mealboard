<?php

use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function unshapedRecipe(string $title, RecipeStatus $status = RecipeStatus::Approved, array $attributes = []): Recipe
{
    $recipe = Recipe::factory()->unshaped()->create([
        'title' => $title,
        'status' => $status,
        'source_url' => 'https://example.com/'.str($title)->slug(),
        'instructions' => "1. Chop the onion.\n2. Fry it.",
        ...$attributes,
    ]);

    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'onion '.$recipe->id])->id,
        ['qty' => 1, 'unit' => 'count', 'note' => 'diced'],
    );

    return $recipe;
}

function shapedAnswer(array $overrides = []): array
{
    return [
        'title' => 'ignored',
        'description' => 'Fried onion.',
        'meal_type' => 'dinner',
        'prep_minutes' => 11,
        'cook_minutes' => 12,
        'servings' => 3,
        'cuisine' => null,
        'tags' => [],
        'source_url' => 'https://example.com/x',
        'tools' => [['alternatives' => ['skillet', 'frying pan'], 'count' => 1]],
        'ingredients' => [['qty' => 1, 'unit' => 'count', 'name' => 'onion', 'prep_note' => 'diced']],
        'steps' => ['Chop the onion.', 'Fry it.'],
        ...$overrides,
    ];
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Http::preventStrayRequests();
});

it('converts approved, pending and rejected recipes without review', function () {
    $recipes = [
        unshapedRecipe('Approved Onions', RecipeStatus::Approved),
        unshapedRecipe('Pending Onions', RecipeStatus::Pending),
        unshapedRecipe('Rejected Onions', RecipeStatus::Rejected),
    ];
    Process::fake(['*' => Process::result(output: json_encode(shapedAnswer()))]);

    $this->artisan('recipes:shape-existing')->assertSuccessful();

    foreach ($recipes as $recipe) {
        $fresh = $recipe->fresh();
        expect($fresh->hasShape())->toBeTrue()
            ->and($fresh->status)->toBe($recipe->status)
            ->and($fresh->recipeTools->first()->alternatives->pluck('word')->all())->toBe(['skillet', 'frying pan'])
            ->and($fresh->cookingSteps->pluck('text')->all())->toBe(['Chop the onion.', 'Fry it.'])
            ->and($fresh->ingredients->first()->pivot->note)->toBe('diced');
    }
    Process::assertRanTimes(fn () => true, 3);
});

it('keeps the stored minutes, servings and meal type and estimates only what is unknown', function () {
    $kept = unshapedRecipe('Kept Values', attributes: [
        'prep_minutes' => 7, 'cook_minutes' => 25, 'servings' => 6, 'meal_type' => MealType::Lunch,
    ]);
    // TheMealDB-style: 0 minutes mean unknown.
    $unknown = unshapedRecipe('Unknown Minutes', attributes: [
        'prep_minutes' => 0, 'cook_minutes' => 0, 'servings' => 4, 'meal_type' => MealType::Dinner,
    ]);
    Process::fake(['*' => Process::result(output: json_encode(shapedAnswer(['meal_type' => 'breakfast', 'servings' => 99])))]);

    $this->artisan('recipes:shape-existing')->assertSuccessful();

    expect($kept->fresh())
        ->prep_minutes->toBe(7)->cook_minutes->toBe(25)->servings->toBe(6)->meal_type->toBe(MealType::Lunch);
    expect($unknown->fresh())
        ->prep_minutes->toBe(11)->cook_minutes->toBe(12)->servings->toBe(4)->meal_type->toBe(MealType::Dinner);
});

it('sends the stored title, ingredients with notes and old method, and never fetches the source url', function () {
    unshapedRecipe('Fetch Me Not');
    Process::fake(['*' => Process::result(output: json_encode(shapedAnswer()))]);

    $this->artisan('recipes:shape-existing')->assertSuccessful();

    Process::assertRan(function ($process) {
        $prompt = $process->command[2] ?? '';

        return str_contains($prompt, 'Fetch Me Not')
            && str_contains($prompt, 'diced')
            && str_contains($prompt, 'Chop the onion.');
    });
    Http::assertNothingSent();
});

it('retries once, then lists a recipe that fails twice by id and title and leaves it untouched', function () {
    $bad = unshapedRecipe('Hopeless Onions');
    $good = unshapedRecipe('Fine Onions');
    Process::fake(function ($process) {
        $prompt = $process->command[2] ?? '';

        return Process::result(output: json_encode(
            str_contains($prompt, 'Hopeless Onions') ? shapedAnswer(['steps' => []]) : shapedAnswer(),
        ));
    });

    $this->artisan('recipes:shape-existing')
        ->expectsOutputToContain("#{$bad->id} Hopeless Onions")
        ->assertFailed();

    expect($bad->fresh()->hasShape())->toBeFalse()
        ->and($bad->fresh()->instructions)->toBe($bad->instructions)
        ->and($bad->fresh()->recipeTools)->toBeEmpty()
        ->and($good->fresh()->hasShape())->toBeTrue();
    // good: 1 call, bad: 2 calls.
    Process::assertRanTimes(fn () => true, 3);
});

it('skips shaped recipes on a rerun and makes no model call for them', function () {
    unshapedRecipe('Needs Shape');
    Recipe::factory()->create(['title' => 'Already Shaped']);
    Process::fake(['*' => Process::result(output: json_encode(shapedAnswer()))]);

    $this->artisan('recipes:shape-existing')->assertSuccessful();
    Process::assertRanTimes(fn () => true, 1);

    $this->artisan('recipes:shape-existing')->assertSuccessful();
    Process::assertRanTimes(fn () => true, 1);
});
