<?php

namespace App\Livewire;

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Models\PlannedMeal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Daily catch-up (/log): yesterday's meals the CURRENT user hasn't logged
 * yet. Only locked/completed weeks are loggable, so drafts never appear.
 */
#[Layout('layouts.app')]
#[Title('Log meals')]
class DailyCatchUp extends Component
{
    public function render(): View
    {
        $slotOrder = array_column(MealSlot::cases(), 'value');

        $meals = PlannedMeal::query()
            ->whereDate('date', today()->subDay()->toDateString())
            ->whereHas('mealPlan', fn ($query) => $query->whereIn('status', [
                MealPlanStatus::Locked->value,
                MealPlanStatus::Completed->value,
            ]))
            ->whereDoesntHave('logs', fn ($query) => $query->where('user_id', auth()->id()))
            ->with('recipe')
            ->get()
            ->sortBy(fn (PlannedMeal $meal) => array_search($meal->slot->value, $slotOrder, true))
            ->values();

        return view('livewire.daily-catch-up', [
            'meals' => $meals,
            'yesterday' => today()->subDay(),
        ]);
    }
}
