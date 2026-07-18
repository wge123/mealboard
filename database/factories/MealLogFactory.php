<?php

namespace Database\Factories;

use App\Models\PlannedMeal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MealLog>
 */
class MealLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ateIt = fake()->boolean(85);

        return [
            'planned_meal_id' => PlannedMeal::factory(),
            'user_id' => User::factory(),
            'ate_it' => $ateIt,
            'rating' => $ateIt ? fake()->numberBetween(1, 5) : null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
