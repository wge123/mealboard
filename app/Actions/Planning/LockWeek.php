<?php

namespace App\Actions\Planning;

use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Models\User;
use LogicException;
use Throwable;

class LockWeek
{
    /**
     * Set when the post-lock vault publish failed. The lock itself always
     * stands — publishing is best-effort (DECISIONS.md #7 push half).
     */
    public ?string $publishWarning = null;

    public function __construct(private PublishMealsMarkdown $publisher) {}

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

        // Publish failure must never roll back the lock: report it and
        // surface a warning instead of rethrowing.
        $this->publishWarning = null;

        try {
            $this->publisher->handle($plan);
        } catch (Throwable $e) {
            report($e);
            $this->publishWarning = 'Week locked, but vault publish failed: '.$e->getMessage();
        }

        return $plan;
    }
}
