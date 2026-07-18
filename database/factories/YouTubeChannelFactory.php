<?php

namespace Database\Factories;

use App\Models\YouTubeChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YouTubeChannel>
 */
class YouTubeChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => 'UC'.fake()->unique()->regexify('[A-Za-z0-9_-]{22}'),
            'name' => fake()->name().' Cooks',
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
