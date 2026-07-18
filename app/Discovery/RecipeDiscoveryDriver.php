<?php

namespace App\Discovery;

interface RecipeDiscoveryDriver
{
    /**
     * Discover up to $n candidate recipes.
     *
     * Each candidate mirrors the recipes schema plus its ingredient rows.
     *
     * @return array<int, array{
     *     title: string,
     *     description: string,
     *     meal_type: string,
     *     prep_minutes: int,
     *     cook_minutes: int,
     *     servings: int,
     *     instructions: string,
     *     cuisine: ?string,
     *     tags: list<string>,
     *     source_url: ?string,
     *     ingredients: list<array{qty: ?float, unit: ?string, name: string, note: ?string}>
     * }>
     */
    public function discover(int $n): array;
}
