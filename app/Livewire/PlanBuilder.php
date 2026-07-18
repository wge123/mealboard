<?php

namespace App\Livewire;

use App\Actions\Planning\AutoFillWeek;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Plan')]
class PlanBuilder extends Component
{
    public ?int $planId = null;

    public ?string $pickerDate = null;

    public ?string $pickerSlot = null;

    public string $pickerSearch = '';

    public function mount(): void
    {
        $this->planId = MealPlan::query()->orderByDesc('week_start_date')->value('id');
    }

    public function updatedPlanId(): void
    {
        $this->closePicker();
    }

    public function createNextWeek(): void
    {
        $monday = Carbon::now()->startOfWeek(CarbonInterface::MONDAY)->addWeek();

        // week_start_date is unique; skip forward past already-planned weeks.
        while (MealPlan::query()->whereDate('week_start_date', $monday->toDateString())->exists()) {
            $monday->addWeek();
        }

        $plan = MealPlan::create([
            'week_start_date' => $monday->toDateString(),
            'status' => MealPlanStatus::Draft,
        ]);

        $this->planId = $plan->id;
        $this->closePicker();
    }

    public function autoFill(): void
    {
        app(AutoFillWeek::class)->handle($this->editablePlan());
    }

    public function openPicker(string $date, string $slot): void
    {
        $this->editablePlan();

        $this->pickerDate = $date;
        $this->pickerSlot = $slot;
        $this->pickerSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerDate = null;
        $this->pickerSlot = null;
        $this->pickerSearch = '';
    }

    public function choose(int $recipeId): void
    {
        $plan = $this->editablePlan();

        if ($this->pickerDate === null || $this->pickerSlot === null) {
            return;
        }

        $slot = MealSlot::from($this->pickerSlot);

        abort_unless($this->weekDates($plan)->contains($this->pickerDate), 422);

        $recipe = Recipe::query()
            ->where('status', RecipeStatus::Approved)
            ->whereIn('meal_type', [$slot->value, MealType::Any->value])
            ->find($recipeId);

        abort_unless($recipe !== null, 404);

        // One meal per plan/date/slot — swapping replaces the recipe in
        // place. The date must be a Carbon instance so the lookup matches the
        // stored datetime format.
        PlannedMeal::query()->updateOrCreate(
            ['meal_plan_id' => $plan->id, 'date' => Carbon::parse($this->pickerDate)->startOfDay(), 'slot' => $slot],
            ['recipe_id' => $recipe->id],
        );

        $this->closePicker();
    }

    public function clearSlot(string $date, string $slot): void
    {
        $plan = $this->editablePlan();

        $plan->plannedMeals()
            ->whereDate('date', $date)
            ->where('slot', $slot)
            ->delete();
    }

    public function render(): View
    {
        $plan = $this->plan()?->load('plannedMeals.recipe');

        $meals = $plan
            ? $plan->plannedMeals->keyBy(fn (PlannedMeal $meal) => $meal->date->toDateString().'|'.$meal->slot->value)
            : collect();

        return view('livewire.plan-builder', [
            'plan' => $plan,
            'plans' => MealPlan::query()->orderByDesc('week_start_date')->get(),
            'days' => $plan ? $this->weekDates($plan)->map(fn (string $date) => Carbon::parse($date)) : collect(),
            'slots' => MealSlot::cases(),
            'meals' => $meals,
            'editable' => $plan?->status === MealPlanStatus::Draft,
            'pickerRecipes' => $this->pickerRecipes(),
        ]);
    }

    private function plan(): ?MealPlan
    {
        return $this->planId ? MealPlan::query()->find($this->planId) : null;
    }

    /**
     * Server-side edit gate: mutations are only allowed on draft plans,
     * regardless of what the UI shows.
     */
    private function editablePlan(): MealPlan
    {
        $plan = $this->plan();

        abort_unless($plan !== null, 404);
        abort_unless($plan->status === MealPlanStatus::Draft, 403, 'This week is no longer editable.');

        return $plan;
    }

    /**
     * @return Collection<int, string>
     */
    private function weekDates(MealPlan $plan): Collection
    {
        return collect(range(0, 4))
            ->map(fn (int $offset) => $plan->week_start_date->copy()->addDays($offset)->toDateString());
    }

    /**
     * @return Collection<int, Recipe>
     */
    private function pickerRecipes(): Collection
    {
        if ($this->pickerSlot === null) {
            return collect();
        }

        return Recipe::query()
            ->where('status', RecipeStatus::Approved)
            ->whereIn('meal_type', [$this->pickerSlot, MealType::Any->value])
            ->when($this->pickerSearch !== '', fn ($query) => $query->where('title', 'like', "%{$this->pickerSearch}%"))
            ->orderBy('title')
            ->limit(30)
            ->get();
    }
}
