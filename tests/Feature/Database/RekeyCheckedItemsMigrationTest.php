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

it('leaves a plan with nothing checked alone', function () {
    $plan = MealPlan::factory()->locked()->create(['checked_items' => null]);

    runRekeyCheckedItemsMigration();

    expect($plan->refresh()->checked_items)->toBeNull();
});
