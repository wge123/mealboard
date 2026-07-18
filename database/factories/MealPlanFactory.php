<?php

namespace Database\Factories;

use App\Enums\MealPlanStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<\App\Models\MealPlan>
 */
class MealPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A unique Monday per plan (meal_plans.week_start_date is unique).
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)
            ->addWeeks(fake()->unique()->numberBetween(0, 500));

        return [
            'week_start_date' => $monday->toDateString(),
            'status' => MealPlanStatus::Draft,
            'locked_at' => null,
            'locked_by' => null,
        ];
    }

    public function locked(?User $locker = null): static
    {
        return $this->state(fn () => [
            'status' => MealPlanStatus::Locked,
            'locked_at' => now(),
            'locked_by' => $locker?->id ?? User::factory(),
        ]);
    }
}
