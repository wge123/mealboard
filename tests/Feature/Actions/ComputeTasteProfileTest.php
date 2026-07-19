<?php

use App\Actions\Planning\ComputeTasteProfile;
use App\Models\Ingredient;
use App\Models\MealLog;
use App\Models\PlannedMeal;
use App\Models\Recipe;

/** A meal log for $recipe with an explicit slot, rating, and ate_it flag. */
function tasteLog(Recipe $recipe, ?int $rating, bool $ate = true, string $slot = 'dinner'): MealLog
{
    return MealLog::factory()->create([
        'planned_meal_id' => PlannedMeal::factory()->create([
            'recipe_id' => $recipe->id,
            'slot' => $slot,
        ])->id,
        'ate_it' => $ate,
        'rating' => $rating,
    ]);
}

function tasteRecipe(array $attributes = []): Recipe
{
    return Recipe::factory()->create(array_merge(['cuisine' => null, 'tags' => []], $attributes));
}

it('surfaces top cuisines by average rating with at least two data points', function () {
    $thai = tasteRecipe(['cuisine' => 'thai']);
    $italian = tasteRecipe(['cuisine' => 'italian']);
    $french = tasteRecipe(['cuisine' => 'french']);

    tasteLog($thai, 5);
    tasteLog($thai, 4);
    tasteLog($italian, 2);
    tasteLog($italian, 1); // avg 1.5 — not favored
    tasteLog($french, 5); // one data point — below the minimum

    expect(app(ComputeTasteProfile::class)->handle()['cuisines'])->toBe(['thai' => 4.5]);
});

it('counts approvals and rejections as cuisine data points', function () {
    tasteRecipe(['cuisine' => 'mexican', 'status' => 'approved']);
    tasteRecipe(['cuisine' => 'mexican', 'status' => 'approved']);
    tasteRecipe(['cuisine' => 'danish', 'status' => 'rejected']);
    tasteRecipe(['cuisine' => 'danish', 'status' => 'rejected']);

    expect(app(ComputeTasteProfile::class)->handle()['cuisines'])->toBe(['mexican' => 4.0]);
});

it('surfaces top tags by average rating', function () {
    $loved = tasteRecipe(['tags' => ['quick', 'cozy']]);
    $meh = tasteRecipe(['tags' => ['fussy']]);

    tasteLog($loved, 5);
    tasteLog($loved, 4);
    tasteLog($meh, 1);
    tasteLog($meh, 2);

    expect(app(ComputeTasteProfile::class)->handle()['tags'])->toBe(['quick' => 4.5, 'cozy' => 4.5]);
});

it('detects ingredients disproportionately present in high vs low rated meals', function () {
    $garlic = Ingredient::factory()->create(['name' => 'garlic']);
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);
    $salt = Ingredient::factory()->create(['name' => 'salt']);

    $loved = tasteRecipe();
    $loved->ingredients()->attach([$garlic->id, $salt->id]);

    $skipped = tasteRecipe();
    $skipped->ingredients()->attach([$cilantro->id, $salt->id]);

    tasteLog($loved, 5);
    tasteLog($loved, 4);
    tasteLog($skipped, null, ate: false);
    tasteLog($skipped, null, ate: false);

    $profile = app(ComputeTasteProfile::class)->handle();

    expect($profile['favoredIngredients'])->toBe(['garlic'])
        // Salt appears equally on both sides — no signal either way.
        ->and($profile['avoidedIngredients'])->toBe(['cilantro']);
});

it('detects slots skipped in more than half of at least two logged opportunities', function () {
    tasteLog(tasteRecipe(), null, ate: false, slot: 'breakfast');
    tasteLog(tasteRecipe(), null, ate: false, slot: 'breakfast');
    tasteLog(tasteRecipe(), null, ate: true, slot: 'breakfast'); // 2/3 skipped

    tasteLog(tasteRecipe(), null, ate: false, slot: 'dinner');
    tasteLog(tasteRecipe(), null, ate: true, slot: 'dinner');
    tasteLog(tasteRecipe(), null, ate: true, slot: 'dinner'); // 1/3 — no pattern

    tasteLog(tasteRecipe(), null, ate: false, slot: 'lunch'); // one log — below minimum

    expect(app(ComputeTasteProfile::class)->handle()['slotPatterns'])->toBe(['breakfast' => 0.67]);
});

it('returns an all-empty profile on a fresh install', function () {
    expect(app(ComputeTasteProfile::class)->handle())->toBe([
        'cuisines' => [],
        'tags' => [],
        'favoredIngredients' => [],
        'avoidedIngredients' => [],
        'slotPatterns' => [],
    ]);
});
