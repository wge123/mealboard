<?php

namespace App\Actions\Discovery;

use App\Actions\Recipes\CreateRecipe;
use App\Enums\RecipeSource;
use App\Models\Recipe;

/**
 * Store one discovered candidate as a pending recipe. A candidate in the
 * recipe shape (tools and steps) is saved with them; a pre-shape candidate
 * (free-text instructions, from the paths that have not moved over yet) is
 * saved as before.
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
        $tools = $candidate['tools'] ?? null;
        $steps = $candidate['steps'] ?? null;
        unset($candidate['ingredients'], $candidate['tools'], $candidate['steps']);

        return $this->createRecipe->handle([
            ...$candidate,
            'source' => RecipeSource::Discovered,
            'discovered_at' => now(),
            ...$extraAttributes,
        ], $ingredients, $tools, $steps);
    }
}
