<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\RecipeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeRequest>
 */
class RecipeRequestFactory extends Factory
{
    protected $model = RecipeRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'query' => 'hibachi for 4 on a flat-top griddle',
            'status' => RequestStatus::Pending,
            'candidates_found' => 0,
            'error' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RequestStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
