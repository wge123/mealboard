<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\DiscoveryRun>
 */
class DiscoveryRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ran_at' => now(),
            'driver' => 'claude-cli',
            'candidates_found' => fake()->numberBetween(0, 3),
            'error' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'candidates_found' => 0,
            'error' => fake()->sentence(),
        ]);
    }
}
