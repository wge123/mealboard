<?php

namespace App\Actions\Recipes;

use App\Exceptions\RecipeShapeRefused;
use App\Models\Recipe;
use App\Support\KitchenToolInventory;

/**
 * Saves a recipe's tools (with alternatives and count) and cooking steps in
 * order, replacing what was there. Each alternative word goes through the
 * tool inventory's resolve; a word that resolves to nothing is kept with no kind.
 */
class SyncRecipeShape
{
    public function __construct(private KitchenToolInventory $inventory) {}

    /**
     * Refuse a supplied shape that has an empty part. Run before any write.
     *
     * @param  array<int, array{alternatives: array<int, string>, count?: ?int}>  $tools
     * @param  array<int, mixed>  $ingredientRows
     * @param  array<int, string>  $steps
     *
     * @throws RecipeShapeRefused
     */
    public function assertComplete(array $tools, array $ingredientRows, array $steps): void
    {
        $missing = [];

        if ($this->cleanTools($tools) === []) {
            $missing[] = 'a kitchen tool';
        }

        if ($ingredientRows === []) {
            $missing[] = 'an ingredient';
        }

        if ($this->cleanSteps($steps) === []) {
            $missing[] = 'a cooking step';
        }

        if ($missing !== []) {
            throw new RecipeShapeRefused('A recipe needs '.implode(', ', $missing).'.');
        }
    }

    /**
     * @param  array<int, array{alternatives: array<int, string>, count?: ?int}>  $tools
     * @param  array<int, string>  $steps
     */
    public function handle(Recipe $recipe, array $tools, array $steps): void
    {
        $recipe->recipeTools()->delete();
        $recipe->cookingSteps()->delete();

        foreach ($this->cleanTools($tools) as $i => $tool) {
            $recipeTool = $recipe->recipeTools()->create(['position' => $i + 1, 'count' => $tool['count']]);

            foreach ($tool['alternatives'] as $j => $word) {
                $recipeTool->alternatives()->create([
                    'position' => $j + 1,
                    'word' => $word,
                    'kitchen_tool_kind_id' => $this->inventory->resolve($word)?->getKey(),
                ]);
            }
        }

        foreach ($this->cleanSteps($steps) as $i => $text) {
            $recipe->cookingSteps()->create(['position' => $i + 1, 'text' => $text]);
        }
    }

    /**
     * @param  array<int, array{alternatives?: array<int, string>, count?: ?int}>  $tools
     * @return list<array{alternatives: list<string>, count: int}>
     */
    private function cleanTools(array $tools): array
    {
        $clean = [];

        foreach ($tools as $tool) {
            $words = array_values(array_filter(
                array_map(fn ($w) => trim((string) $w), $tool['alternatives'] ?? []),
                fn ($w) => $w !== '',
            ));

            if ($words === []) {
                continue;
            }

            $count = (int) ($tool['count'] ?? 1);

            if ($count < 1) {
                throw new RecipeShapeRefused('A kitchen tool count must be at least 1.');
            }

            $clean[] = ['alternatives' => $words, 'count' => $count];
        }

        return $clean;
    }

    /**
     * @param  array<int, string>  $steps
     * @return list<string>
     */
    private function cleanSteps(array $steps): array
    {
        return array_values(array_filter(
            array_map(fn ($s) => trim((string) $s), $steps),
            fn ($s) => $s !== '',
        ));
    }
}
