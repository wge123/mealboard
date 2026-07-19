<?php

namespace Database\Factories;

use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MealPlan>
 */
class MealPlanFactory extends Factory
{
    private static int $weekSequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A unique Monday per plan (meal_plans.week_start_date is unique).
        // Sequential far-future weeks: random draws could collide with the
        // near-now dates tests set explicitly.
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)
            ->addWeeks(1000 + self::$weekSequence++);

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
