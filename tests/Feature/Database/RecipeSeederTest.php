<?php

use App\Models\Recipe;
use App\Models\RecipeToolAlternative;
use Database\Seeders\RecipeSeeder;

it('seeds only recipes in the recipe shape', function () {
    $this->seed(RecipeSeeder::class);

    expect(Recipe::count())->toBeGreaterThan(0);

    Recipe::all()->each(fn (Recipe $recipe) => expect($recipe->hasShape())->toBeTrue("{$recipe->title} has no shape"));
});

it('seeds tool words that all resolve to a kitchen tool kind', function () {
    $this->seed(RecipeSeeder::class);

    expect(RecipeToolAlternative::query()->whereNull('kitchen_tool_kind_id')->count())->toBe(0);
});
