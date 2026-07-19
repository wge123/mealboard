<?php

namespace Database\Factories;

use App\Models\BrainNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrainNote>
 */
class BrainNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'wiki/concepts/'.fake()->unique()->slug().'.md',
            'content' => fake()->paragraph(),
            'fetched_at' => now(),
        ];
    }
}
