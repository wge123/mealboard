<?php

namespace App\Actions\Planning;

use App\Enums\MealSlot;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Support\SlotKey;
use Illuminate\Support\Collection;

/**
 * What one fill has taken and made so far: the slots no longer open, how
 * often each recipe is on the week, the meals created, and the reasons a slot
 * the household asked about stayed empty.
 */
final class FillState
{
    /** @var Collection<int, PlannedMeal> */
    public Collection $created;

    /** @var list<string> one plain sentence per slot left empty, for the week notice */
    public array $unfilled = [];

    /**
     * @param  Collection<string, mixed>  $filled  slot keys no longer open
     * @param  array<int, int>  $useCounts  uses per recipe id
     */
    public function __construct(public Collection $filled, public array $useCounts)
    {
        $this->created = collect();
    }

    public function place(MealPlan $plan, Recipe $recipe, string $date, MealSlot $slot): void
    {
        $this->useCounts[$recipe->id] = ($this->useCounts[$recipe->id] ?? 0) + 1;
        $this->filled->put(SlotKey::make($date, $slot), true);

        $this->created->push($plan->plannedMeals()->create([
            'recipe_id' => $recipe->id,
            'date' => $date,
            'slot' => $slot,
        ]));
    }
}
