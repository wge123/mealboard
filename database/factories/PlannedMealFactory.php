<?php

namespace Database\Factories;

use App\Enums\MealSlot;
use App\Models\MealPlan;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PlannedMeal>
 */
class PlannedMealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_plan_id' => MealPlan::factory(),
            'recipe_id' => Recipe::factory(),
            'date' => fake()->dateTimeBetween('now', '+6 days')->format('Y-m-d'),
            'slot' => fake()->randomElement(MealSlot::cases()),
        ];
    }
}
