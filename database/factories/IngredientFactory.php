<?php

namespace Database\Factories;

use App\Enums\IngredientCategory;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'category' => fake()->randomElement(IngredientCategory::cases()),
            'is_pantry_staple' => false,
        ];
    }

    public function pantryStaple(): static
    {
        return $this->state(fn () => [
            'category' => IngredientCategory::Pantry,
            'is_pantry_staple' => true,
        ]);
    }
}
