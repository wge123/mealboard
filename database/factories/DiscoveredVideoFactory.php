<?php

namespace Database\Factories;

use App\Enums\VideoClassification;
use App\Models\DiscoveredVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoveredVideo>
 */
class DiscoveredVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{11}'),
            'channel_id' => 'UC'.fake()->regexify('[A-Za-z0-9_-]{22}'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'published_at' => fake()->dateTimeBetween('-1 month'),
            'classification' => null,
            'score' => null,
            'processed_at' => null,
            'error' => null,
        ];
    }

    public function likelyRecipe(int $score = 80): static
    {
        return $this->state(fn () => [
            'classification' => VideoClassification::LikelyRecipe,
            'score' => $score,
        ]);
    }

    public function notRecipe(int $score = 10): static
    {
        return $this->state(fn () => [
            'classification' => VideoClassification::NotRecipe,
            'score' => $score,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn () => ['processed_at' => now()]);
    }
}
