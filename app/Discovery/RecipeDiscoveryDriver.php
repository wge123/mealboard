<?php

namespace App\Discovery;

interface RecipeDiscoveryDriver
{
    /**
     * Discover up to $n candidate recipes.
     *
     * Each candidate mirrors the recipes schema plus its ingredient rows. A
     * candidate in the recipe shape (see RecipeOutputFormat) carries `tools`,
     * `steps` and ingredient `prep_note`; a driver that has not moved over yet
     * still returns free-text `instructions` and ingredient `note`.
     *
     * @return array<int, array{
     *     title: string,
     *     description: string,
     *     meal_type: string,
     *     prep_minutes: int,
     *     cook_minutes: int,
     *     servings: int,
     *     instructions?: string,
     *     tools?: list<array{alternatives: list<string>, count: int}>,
     *     steps?: list<string>,
     *     cuisine: ?string,
     *     tags: list<string>,
     *     source_url: ?string,
     *     ingredients: list<array{qty: ?float, unit: ?string, name: string, note?: ?string, prep_note?: ?string}>
     * }>
     */
    public function discover(int $n): array;
}
