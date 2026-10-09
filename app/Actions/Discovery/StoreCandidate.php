<?php

namespace App\Actions\Discovery;

use App\Actions\Recipes\CreateRecipe;
use App\Enums\RecipeSource;
use App\Models\Recipe;

/**
 * Store one discovered candidate (already checked, in the recipe shape) as a
 * pending recipe, with its tools and steps.
 */
class StoreCandidate
{
    public function __construct(private CreateRecipe $createRecipe) {}

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $extraAttributes
     */
    public function handle(array $candidate, array $extraAttributes = []): Recipe
    {
        $ingredients = $candidate['ingredients'];
        $tools = $candidate['tools'];
        $steps = $candidate['steps'];
        unset($candidate['ingredients'], $candidate['tools'], $candidate['steps']);

        return $this->createRecipe->handle([
            ...$candidate,
            'source' => RecipeSource::Discovered,
            'discovered_at' => now(),
            ...$extraAttributes,
        ], $ingredients, $tools, $steps);
    }
}
