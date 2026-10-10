<?php

use App\Enums\DiscoveryLane;
use App\Exceptions\HouseholdPreferenceRefused;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\HouseholdPreferences;

function shapedCandidate(array $overrides = []): array
{
    return array_merge([
        'title' => 'Lemon Salmon',
        'description' => 'Quick salmon.',
        'meal_type' => 'dinner',
        'prep_minutes' => 10,
        'cook_minutes' => 15,
        'servings' => 2,
        'cuisine' => null,
        'tags' => [],
        'source_url' => 'https://example.com/salmon',
        'tools' => [['alternatives' => ['skillet'], 'count' => 1]],
        'ingredients' => [
            ['qty' => 2, 'unit' => null, 'name' => 'salmon fillets', 'prep_note' => null],
            ['qty' => 1, 'unit' => null, 'name' => 'lemon', 'prep_note' => 'juiced'],
        ],
        'steps' => ['Sear.', 'Serve.'],
    ], $overrides);
}

/** @param  list<string>  $names */
function candidateWithIngredients(array $names, array $overrides = []): array
{
    return shapedCandidate(array_merge([
        'ingredients' => array_map(fn (string $name) => ['qty' => 1, 'unit' => null, 'name' => $name, 'prep_note' => null], $names),
    ], $overrides));
}

it('starts at 30 minutes, 10 ingredients, a household of 2 and nothing avoided', function () {
    $preferences = new HouseholdPreferences;

    expect($preferences->weekdayLimits())->toBe(['minutes' => 30, 'ingredients' => 10])
        ->and($preferences->householdSize())->toBe(2)
        ->and($preferences->avoidedIngredients()->all())->toBe([]);
});

it('saves the weekday limits and the household size', function () {
    $preferences = new HouseholdPreferences;

    $preferences->setWeekdayLimits(45, 12);
    $preferences->setHouseholdSize(4);

    expect($preferences->weekdayLimits())->toBe(['minutes' => 45, 'ingredients' => 12])
        ->and($preferences->householdSize())->toBe(4);
});

it('refuses a limit or household size under 1 and keeps the stored value', function (string $method, array $arguments) {
    $preferences = new HouseholdPreferences;

    expect(fn () => $preferences->{$method}(...$arguments))->toThrow(HouseholdPreferenceRefused::class);

    expect($preferences->weekdayLimits())->toBe(['minutes' => 30, 'ingredients' => 10])
        ->and($preferences->householdSize())->toBe(2);
})->with([
    'zero minutes' => ['setWeekdayLimits', [0, 10]],
    'negative ingredients' => ['setWeekdayLimits', [30, -1]],
    'zero people' => ['setHouseholdSize', [0]],
]);

it('stores avoided words trimmed and lowercased, alphabetical, and removes them', function () {
    $preferences = new HouseholdPreferences;

    $preferences->avoid('  Cilantro ');
    $preferences->avoid('anchovies');

    expect($preferences->avoidedIngredients()->all())->toBe(['anchovies', 'cilantro']);

    $preferences->stopAvoiding('CILANTRO');

    expect($preferences->avoidedIngredients()->all())->toBe(['anchovies']);
});

it('refuses a blank or duplicate avoided word', function (string $word) {
    $preferences = new HouseholdPreferences;
    $preferences->avoid('cilantro');

    $preferences->avoid($word);
})->with(['   ', 'Cilantro'])->throws(HouseholdPreferenceRefused::class);

it('refuses a scheduled candidate over the time limit and passes one with an unknown time', function () {
    $preferences = new HouseholdPreferences;

    expect($preferences->refusals(shapedCandidate(['prep_minutes' => 20, 'cook_minutes' => 15]), DiscoveryLane::Scheduled))
        ->toHaveCount(1)
        ->and($preferences->refusals(shapedCandidate(['prep_minutes' => 20, 'cook_minutes' => 15]), DiscoveryLane::Scheduled)[0])
        ->toContain('35 minutes')
        ->and($preferences->refusals(shapedCandidate(['prep_minutes' => 15, 'cook_minutes' => 15]), DiscoveryLane::Scheduled))->toBe([])
        ->and($preferences->refusals(shapedCandidate(['prep_minutes' => 0, 'cook_minutes' => 0]), DiscoveryLane::Scheduled))->toBe([]);
});

it('refuses a scheduled candidate over the ingredient limit without counting pantry staples', function () {
    $preferences = new HouseholdPreferences;
    $preferences->setWeekdayLimits(30, 3);
    Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    Ingredient::factory()->pantryStaple()->create(['name' => 'olive oil']);

    $four = candidateWithIngredients(['chicken', 'rice', 'peas', 'lemon']);
    $threePlusStaples = candidateWithIngredients(['chicken', 'rice', 'peas', 'Salt', 'olive oil']);

    expect($preferences->refusals($four, DiscoveryLane::Scheduled))->toHaveCount(1)
        ->and($preferences->refusals($four, DiscoveryLane::Scheduled)[0])->toContain('4 ingredients')
        ->and($preferences->refusals($threePlusStaples, DiscoveryLane::Scheduled))->toBe([]);
});

it('counts an ingredient listed twice once toward the ingredient limit', function () {
    $preferences = new HouseholdPreferences;
    $preferences->setWeekdayLimits(30, 3);

    $listedTwice = candidateWithIngredients(['chicken', 'Rice', 'rice', 'peas', 'chicken']);

    expect($preferences->refusals($listedTwice, DiscoveryLane::Scheduled))->toBe([]);
});

it('refuses an avoided ingredient as a whole word, ignoring case, on both lanes', function () {
    $preferences = new HouseholdPreferences;
    $preferences->avoid('cilantro');

    $fresh = candidateWithIngredients(['Fresh Cilantro', 'lime']);
    $lookalike = candidateWithIngredients(['cilantrolike herb', 'lime']);

    foreach (DiscoveryLane::cases() as $lane) {
        expect($preferences->refusals($fresh, $lane))->toBe(['contains avoided ingredient: cilantro'])
            ->and($preferences->refusals($lookalike, $lane))->toBe([]);
    }
});

it('ignores the weekday limits on the request lane but not the avoided ingredients', function () {
    $preferences = new HouseholdPreferences;
    $preferences->setWeekdayLimits(10, 1);
    $preferences->avoid('anchovies');

    $long = candidateWithIngredients(['steak', 'rice', 'eggs'], ['prep_minutes' => 25, 'cook_minutes' => 35]);
    $withAnchovies = candidateWithIngredients(['anchovies']);

    expect($preferences->refusals($long, DiscoveryLane::Request))->toBe([])
        ->and($preferences->refusals($long, DiscoveryLane::Scheduled))->toHaveCount(2)
        ->and($preferences->refusals($withAnchovies, DiscoveryLane::Request))->toHaveCount(1);
});

it('marks a staple, creating the ingredient when no recipe uses it yet', function () {
    $preferences = new HouseholdPreferences;

    $soy = $preferences->setStaple(' Soy Sauce ', true);

    expect($soy->name)->toBe('soy sauce')
        ->and($soy->is_pantry_staple)->toBeTrue()
        ->and($preferences->staples()->all())->toBe(['soy sauce'])
        ->and(Ingredient::where('name', 'soy sauce')->count())->toBe(1);

    $preferences->setStaple('soy sauce', false);

    expect($preferences->staples()->all())->toBe([])
        ->and(Ingredient::where('name', 'soy sauce')->count())->toBe(1);
});

it('refuses to mark a blank name as a staple', function () {
    (new HouseholdPreferences)->setStaple('  ', true);
})->throws(HouseholdPreferenceRefused::class);

it('finds the avoided words in many recipes, with a key for every recipe', function () {
    $preferences = new HouseholdPreferences;
    $preferences->avoid('cilantro');

    $herby = Recipe::factory()->create();
    $herby->ingredients()->attach(Ingredient::factory()->create(['name' => 'fresh cilantro'])->id, ['qty' => 1, 'unit' => null, 'note' => null]);
    $plain = Recipe::factory()->create();

    expect($preferences->avoidedInRecipes([$herby, $plain]))->toBe([$herby->id => ['cilantro'], $plain->id => []]);
});
