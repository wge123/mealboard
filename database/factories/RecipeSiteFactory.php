<?php

namespace Database\Factories;

use App\Models\RecipeSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeSite>
 */
class RecipeSiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feed_url' => 'https://'.fake()->unique()->domainName().'/feed/',
            'name' => fake()->name().' Kitchen',
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
