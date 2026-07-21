<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
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

    public function discovered(): static
    {
        return $this->state(fn () => [
            'source' => RecipeSource::Discovered,
            'source_url' => fake()->url(),
            'discovered_at' => now(),
        ]);
    }
}
