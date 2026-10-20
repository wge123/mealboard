<?php

use App\Models\RecipeSite;

it('lists Love and Lemons and Just One Cookbook as active recipe sites after migrating', function () {
    expect(RecipeSite::query()->orderBy('name')->get(['name', 'feed_url', 'active'])->toArray())->toBe([
        ['name' => 'Just One Cookbook', 'feed_url' => 'https://www.justonecookbook.com/feed/', 'active' => true],
        ['name' => 'Love and Lemons', 'feed_url' => 'https://www.loveandlemons.com/feed/', 'active' => true],
    ]);
});

it('adds the first sites once, leaving a paused one as the household left it', function () {
    RecipeSite::query()->where('name', 'Love and Lemons')->update(['active' => false]);

    $migration = require base_path('database/migrations/2026_10_20_000003_add_first_recipe_sites.php');
    $migration->up();

    expect(RecipeSite::count())->toBe(2)
        ->and(RecipeSite::firstWhere('name', 'Love and Lemons')->active)->toBeFalse();
});
