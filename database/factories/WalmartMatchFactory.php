<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\WalmartMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalmartMatch>
 */
class WalmartMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'product_url' => 'https://www.walmart.com/ip/'.fake()->unique()->numberBetween(1000000, 9999999),
            'product_name' => fake()->words(3, true),
            'last_confirmed_at' => now(),
        ];
    }
}
