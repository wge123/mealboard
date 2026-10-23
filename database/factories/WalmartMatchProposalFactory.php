<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\WalmartMatchProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalmartMatchProposal>
 */
class WalmartMatchProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'product_url' => 'https://www.walmart.com/ip/'.fake()->slug(2).'/'.fake()->unique()->numberBetween(10000000, 99999999),
            'product_name' => fake()->words(3, true),
            'confidence' => 'high',
        ];
    }
}
