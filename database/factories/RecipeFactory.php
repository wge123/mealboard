<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->paragraph(),
            'source_url' => fake()->url(),
            'source' => RecipeSource::Manual,
            'status' => RecipeStatus::Pending,
            'meal_type' => fake()->randomElement(MealType::cases()),
            'prep_minutes' => fake()->numberBetween(5, 45),
            'cook_minutes' => fake()->numberBetween(0, 90),
            'servings' => fake()->numberBetween(1, 8),
            'instructions' => '1. '.fake()->sentence()."\n2. ".fake()->sentence(),
            'cuisine' => fake()->optional()->randomElement(['italian', 'mexican', 'thai', 'french', 'japanese']),
            'tags' => fake()->randomElements(['quick', 'healthy', 'vegetarian', 'comfort', 'spicy'], 2),
            'image_url' => null,
            'discovered_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn () => [
            'status' => RecipeStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver?->id ?? User::factory(),
        ]);
    }

    /**
     * Every recipe has the recipe shape unless a test asks for ->unshaped().
     */
    public function configure(): static
    {
        return $this->shaped();
    }

    /**
     * A recipe with the recipe shape: one tool (skillet), one ingredient with
     * a prep note, and two cooking steps. The old method stays set. This is
     * the default.
     */
    public function shaped(): static
    {
        return $this->afterCreating(function (Recipe $recipe) {
            if ($recipe->hasShape()) {
                return;
            }

            $tool = $recipe->recipeTools()->create(['position' => 1, 'count' => 1]);
            $tool->alternatives()->create([
                'position' => 1,
                'word' => 'skillet',
                'kitchen_tool_kind_id' => KitchenToolKind::where('name', 'skillet')->value('id'),
            ]);

            $recipe->ingredients()->attach(
                Ingredient::factory()->create()->id,
                ['qty' => 1, 'unit' => 'count', 'note' => 'chopped'],
            );

            foreach ([1, 2] as $position) {
                $recipe->cookingSteps()->create(['position' => $position, 'text' => fake()->sentence()]);
            }
        });
    }

    /**
     * A recipe from before the shape: no tools, no ingredients, no steps,
     * just the old free-text method. Runs after the default shape is added.
     */
    public function unshaped(): static
    {
        return $this->afterCreating(function (Recipe $recipe) {
            $recipe->recipeTools()->delete();
            $recipe->cookingSteps()->delete();
            $recipe->ingredients()->detach();
        });
    }

    public function discovered(): static
    {
        return $this->state(fn () => [
            'source' => RecipeSource::Discovered,
            'source_url' => fake()->url(),
            'discovered_at' => now(),
        ]);
    }
}
