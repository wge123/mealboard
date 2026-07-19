<?php

namespace App\Livewire;

use App\Enums\MealPlanStatus;
use App\Models\MealLog;
use App\Models\PlannedMeal;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Per-user quick-log controls for one planned meal, rendered inside the plan
 * builder (locked/completed weeks) and the /log daily catch-up page. Writes
 * upsert meal_logs on (planned_meal_id, user_id), so each user keeps exactly
 * one editable log per meal.
 *
 * "Past slot" is DATE-ONLY by design: a slot is loggable when its date is
 * today or earlier, so today's dinner can be logged at breakfast time.
 * Slot-time bookkeeping isn't worth the complexity for a two-person
 * household.
 */
class MealLogControls extends Component
{
    public PlannedMeal $plannedMeal;

    public ?bool $ateIt = null;

    public ?int $rating = null;

    public string $notes = '';

    public function mount(PlannedMeal $plannedMeal): void
    {
        $this->plannedMeal = $plannedMeal;

        $log = MealLog::query()
            ->where('planned_meal_id', $plannedMeal->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($log !== null) {
            $this->ateIt = $log->ate_it;
            $this->rating = $log->rating;
            $this->notes = (string) $log->notes;
        }
    }

    public function setAte(bool $ate): void
    {
        $this->assertLoggable();

        $this->ateIt = $ate;
        $this->save();
    }

    public function setRating(int $rating): void
    {
        abort_unless($rating >= 1 && $rating <= 5, 422);
        $this->assertLoggable();
        abort_unless($this->ateIt !== null, 422, 'Answer "Ate it?" first.');

        // Tapping the current star again clears the (nullable) rating.
        $this->rating = $this->rating === $rating ? null : $rating;
        $this->save();
    }

    public function updatedNotes(): void
    {
        $this->assertLoggable();
        abort_unless($this->ateIt !== null, 422, 'Answer "Ate it?" first.');

        $this->save();
    }

    public function render(): View
    {
        return view('livewire.meal-log-controls');
    }

    private function save(): void
    {
        MealLog::query()->updateOrCreate(
            ['planned_meal_id' => $this->plannedMeal->id, 'user_id' => auth()->id()],
            ['ate_it' => $this->ateIt, 'rating' => $this->rating, 'notes' => $this->notes !== '' ? $this->notes : null],
        );
    }

    /**
     * Week-locking permits logging (unlike plan edits): logging is only OPEN
     * on locked/completed weeks, and only for past slots (date-only, today
     * included — see class docblock).
     */
    private function assertLoggable(): void
    {
        $status = $this->plannedMeal->mealPlan->status;

        abort_unless(
            in_array($status, [MealPlanStatus::Locked, MealPlanStatus::Completed], true),
            403,
            'Logging opens once the week is locked.',
        );

        abort_unless(
            $this->plannedMeal->date->startOfDay()->lte(today()),
            403,
            'This meal is still in the future.',
        );
    }
}
