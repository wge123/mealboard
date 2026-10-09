<?php

namespace App\Console\Commands;

use App\Actions\Recipes\UpdateRecipe;
use App\Discovery\ClaudeCliFailed;
use App\Discovery\ShapingPass;
use App\Exceptions\RecipeShapeRefused;
use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * One-off backfill: converts every recipe without the recipe shape (any
 * status) through the shaping pass, with its single retry, and saves each
 * success straight away. Works from the stored title, ingredients and old
 * method only; it never fetches the source URL. Recipes that already have the
 * shape are skipped, so a rerun only retries the failures.
 *
 * What the recipe already stores counts as known and is kept: minutes,
 * servings and meal type are sent to the pass as source values. A stored 0
 * for prep or cook minutes (TheMealDB recipes) and a 0 for servings count as
 * unknown and are estimated.
 *
 * It reads the old free-text method column, which the migration that drops
 * that column removes; run this command first. Delete it after landing.
 */
class ShapeExistingRecipes extends Command
{
    protected $signature = 'recipes:shape-existing';

    protected $description = 'Convert recipes that lack the recipe shape (tools, mise en place, steps) with the shaping pass';

    public function handle(ShapingPass $shapingPass, UpdateRecipe $updateRecipe): int
    {
        if (! Schema::hasColumn('recipes', 'instructions')) {
            $this->error('The old method column is already dropped; there is nothing left to convert.');

            return self::FAILURE;
        }

        $converted = 0;
        $failures = [];

        Recipe::query()->with('ingredients')->orderBy('id')->each(function (Recipe $recipe) use ($shapingPass, $updateRecipe, &$converted, &$failures) {
            if ($recipe->hasShape()) {
                return;
            }

            try {
                $check = $shapingPass->withRetry($this->raw($recipe));

                if (! $check->passes()) {
                    $failures[] = [$recipe, implode('; ', $check->errors)];

                    return;
                }

                $candidate = $check->candidate;

                $updateRecipe->handle(
                    $recipe,
                    [
                        'description' => $candidate['description'],
                        'meal_type' => $candidate['meal_type'],
                        'prep_minutes' => $candidate['prep_minutes'],
                        'cook_minutes' => $candidate['cook_minutes'],
                        'servings' => $candidate['servings'],
                        'cuisine' => $candidate['cuisine'],
                        'tags' => $candidate['tags'],
                    ],
                    $candidate['ingredients'],
                    $candidate['tools'],
                    $candidate['steps'],
                );
            } catch (ClaudeCliFailed|RecipeShapeRefused $e) {
                // The claude CLI failing (or a shape the write refuses) fails
                // this recipe only; it is listed below and left untouched.
                $failures[] = [$recipe, $e->getMessage()];

                return;
            }

            $converted++;
            $this->line("Shaped #{$recipe->id} {$recipe->title}");
        });

        $this->info("Converted {$converted} recipe(s).");

        if ($failures === []) {
            return self::SUCCESS;
        }

        $this->error(count($failures).' recipe(s) could not be converted and were left untouched:');

        foreach ($failures as [$recipe, $reason]) {
            $this->line("  #{$recipe->id} {$recipe->title}: {$reason}");
        }

        return self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(Recipe $recipe): array
    {
        return [
            'title' => $recipe->title,
            'source_url' => $recipe->source_url,
            'description' => $recipe->description,
            'cuisine' => $recipe->cuisine,
            'tags' => $recipe->tags,
            'ingredients' => $recipe->ingredients->map(fn ($ingredient) => [
                'qty' => $ingredient->pivot->qty,
                'unit' => $ingredient->pivot->unit,
                'name' => $ingredient->name,
                'note' => $ingredient->pivot->note,
            ])->all(),
            'method' => (string) $recipe->instructions,
            'prep_minutes' => $recipe->prep_minutes ?: null,
            'cook_minutes' => $recipe->cook_minutes ?: null,
            'servings' => $recipe->servings ?: null,
            'meal_type' => $recipe->meal_type?->value,
        ];
    }
}
