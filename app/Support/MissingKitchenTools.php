<?php

namespace App\Support;

use App\Models\Recipe;
use Illuminate\Support\Collection;

/**
 * The missing-tools query: which tool entries of a recipe the household has
 * no owned kind for. Worked out on every call and never stored, so marking a
 * kind owned clears the flag at once. An unknown kind is never owned, and a
 * recipe without the recipe shape has no missing tools.
 */
class MissingKitchenTools
{
    /**
     * @return list<MissingTool>
     */
    public function for(Recipe $recipe): array
    {
        return $this->forMany([$recipe])[$recipe->id];
    }

    /**
     * The same answer for many recipes in a fixed number of queries, keyed by
     * recipe id; every given recipe has a key (empty when nothing is missing).
     *
     * @param  iterable<Recipe>  $recipes
     * @return array<int, list<MissingTool>>
     */
    public function forMany(iterable $recipes): array
    {
        $ids = collect($recipes)->pluck('id')->all();
        $result = array_fill_keys($ids, []);

        if ($ids === []) {
            return $result;
        }

        $shaped = Recipe::query()
            ->whereIn('id', $ids)
            ->has('recipeTools')
            ->has('cookingSteps')
            ->has('ingredients')
            ->with('recipeTools.alternatives.kind')
            ->get();

        foreach ($shaped as $recipe) {
            $result[$recipe->id] = $recipe->recipeTools
                ->filter(fn ($tool) => ! $tool->alternatives->contains(fn ($alt) => $alt->kind?->owned === true))
                ->map(fn ($tool) => new MissingTool($tool, $tool->alternatives
                    ->map(fn ($alt) => ['word' => $alt->word, 'kind' => $alt->kind])
                    ->values()
                    ->all()))
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * Ids of the recipes (of those given) that have a missing tool.
     *
     * @param  iterable<Recipe>  $recipes
     * @return Collection<int, int>
     */
    public function flaggedIds(iterable $recipes): Collection
    {
        return collect($this->forMany($recipes))->filter(fn (array $missing) => $missing !== [])->keys();
    }
}
