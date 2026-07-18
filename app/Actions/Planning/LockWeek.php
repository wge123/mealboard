<?php

namespace App\Actions\Planning;

use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Models\User;
use LogicException;

class LockWeek
{
    public function handle(MealPlan $plan, User $actor): MealPlan
    {
        if ($plan->status !== MealPlanStatus::Draft) {
            throw new LogicException('Only draft plans can be locked.');
        }

        $plan->update([
            'status' => MealPlanStatus::Locked,
            'locked_at' => now(),
            'locked_by' => $actor->id,
        ]);

        return $plan;
    }
}
