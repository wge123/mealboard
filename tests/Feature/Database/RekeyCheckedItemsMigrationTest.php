<?php

use App\Models\MealPlan;

function runRekeyCheckedItemsMigration(): void
{
    $migration = require database_path('migrations/2026_10_08_000000_rekey_checked_items_by_merge_bucket.php');

    $migration->up();
}

it('rewrites checked keys from display unit to merge bucket', function () {
    $plan = MealPlan::factory()->locked()->create([
        'checked_items' => ['butter|cup', 'flour|kg', 'milk|ml', 'cumin|tsp', 'rice|g', 'stock|l', 'cheddar|oz', 'parsley|', 'yellow onion|count'],
    ]);

    runRekeyCheckedItemsMigration();

    expect($plan->refresh()->checked_items)->toBe(
        ['butter|spoon', 'flour|mass', 'milk|volume', 'cumin|spoon', 'rice|mass', 'stock|volume', 'cheddar|oz', 'parsley|', 'yellow onion|count'],
    );
});

it('refuses a stored key with no unit separator as corrupt', function () {
    MealPlan::factory()->locked()->create(['checked_items' => ['flour|kg', 'flour']]);

    expect(fn () => runRekeyCheckedItemsMigration())
        ->toThrow(UnexpectedValueException::class, 'Checked key "flour" has no "|" separator');
});

it('cannot be rolled back', function () {
    $migration = require database_path('migrations/2026_10_08_000000_rekey_checked_items_by_merge_bucket.php');

    expect(fn () => $migration->down())->toThrow(LogicException::class, 'not reversible');
});

it('leaves a plan with nothing checked alone', function () {
    $plan = MealPlan::factory()->locked()->create(['checked_items' => null]);

    runRekeyCheckedItemsMigration();

    expect($plan->refresh()->checked_items)->toBeNull();
});
