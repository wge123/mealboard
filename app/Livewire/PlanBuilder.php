<?php

namespace App\Livewire;

use App\Actions\Planning\AutoFillWeek;
use App\Actions\Planning\ComputeTasteProfile;
use App\Actions\Planning\LockWeek;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Support\HouseholdPreferences;
use App\Support\MissingKitchenTools;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['width' => 'wide'])]
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
        $this->fillNotes = [];
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
        $this->fillNotes = [];
        $this->closePicker();
    }

    /** The week check-in sheet is open. */
    public bool $checkInOpen = false;

    /**
     * The slots ticked as skipped in the open sheet, as "YYYY-MM-DD|slot"
     * keys. Held here until the sheet is submitted.
     *
     * @var list<string>
     */
    public array $skips = [];

    /** @var list<string> */
    public array $quickNights = [];

    /** The cuisine chosen as this week's craving in the open sheet; '' for none. */
    public string $craving = '';

    /**
     * Why slots stayed empty after the last fill, for the week notice. Held
     * here, not derived: only the fill knows what it was asked and could not
     * do. Cleared when the household changes week or fills again.
     *
     * @var list<string>
     */
    public array $fillNotes = [];

    public function toggleQuickNight(string $date): void
    {
        $plan = $this->editablePlan();

        abort_unless($this->weekDates($plan)->contains($date), 422);

        $this->quickNights = in_array($date, $this->quickNights, true)
            ? array_values(array_diff($this->quickNights, [$date]))
            : [...$this->quickNights, $date];
    }

    /** Open the week check-in, pre-filled with the answers saved on the week. */
    public function openCheckIn(): void
    {
        $plan = $this->editablePlan();

        $this->skips = $plan->skippedSlots()->all();
        $this->quickNights = $plan->quickNights()->all();
        $this->checkInOpen = true;
    }

    public function closeCheckIn(): void
    {
        $this->checkInOpen = false;
        $this->skips = [];
        $this->quickNights = [];
    }

    public function toggleSkip(string $date, string $slot): void
    {
        $key = $this->skipKey($this->editablePlan(), $date, $slot);

        $this->skips = in_array($key, $this->skips, true)
            ? array_values(array_diff($this->skips, [$key]))
            : [...$this->skips, $key];
    }

    /** Skip all three slots of a day, or clear them when they are all skipped already. */
    public function toggleSkipDay(string $date): void
    {
        $plan = $this->editablePlan();

        $keys = array_map(fn (MealSlot $slot) => $this->skipKey($plan, $date, $slot->value), MealSlot::cases());
        $missing = array_values(array_diff($keys, $this->skips));

        $this->skips = $missing === []
            ? array_values(array_diff($this->skips, $keys))
            : [...$this->skips, ...$missing];
    }

    /** Save the sheet's answers on the week, then fill around them. */
    public function submitCheckIn(): void
    {
        $plan = $this->editablePlan();

        $order = collect(MealSlot::cases())->map->value->flip();
        $plan->saveSkippedSlots(collect($this->skips)->sortBy(function (string $key) use ($order) {
            [$date, $slot] = explode('|', $key);

            return $date.'|'.$order[$slot];
        })->values());
        $plan->saveQuickNights($this->quickNights);

        $this->closeCheckIn();
        $this->autoFill();
    }

    /** Fill with the answers already saved on the week; the sheet's own edits are dropped. */
    public function justFill(): void
    {
        $this->editablePlan();

        $this->closeCheckIn();
        $this->autoFill();
    }

    private function skipKey(MealPlan $plan, string $date, string $slot): string
    {
        abort_unless($this->weekDates($plan)->contains($date), 422);
        abort_unless(MealSlot::tryFrom($slot) !== null, 422);

        return $date.'|'.$slot;
    }

    public function autoFill(): void
    {
        $this->fillNotes = app(AutoFillWeek::class)->fill($this->editablePlan())->unfilled;
    }

    /** Warning surfaced when the post-lock vault publish failed. */
    public ?string $publishWarning = null;

    public function lock(): void
    {
        $lockWeek = app(LockWeek::class);

        $lockWeek->handle($this->editablePlan(), auth()->user());

        $this->publishWarning = $lockWeek->publishWarning;
    }

    /** Tick or untick "Looks good" on a day of a suggested week; saved on the week for the whole household. */
    public function toggleReviewed(string $date): void
    {
        $plan = $this->editablePlan();

        abort_unless($this->weekDates($plan)->contains($date), 422);

        $reviewed = collect($plan->reviewed_dates ?? []);

        $plan->update(['reviewed_dates' => $reviewed->contains($date)
            ? $reviewed->reject(fn (string $d) => $d === $date)->values()->all()
            : $reviewed->push($date)->values()->all()]);
    }

    public function markCompleted(): void
    {
        $plan = $this->plan();

        abort_unless($plan !== null, 404);
        abort_unless($plan->status === MealPlanStatus::Locked, 403, 'Only locked weeks can be completed.');
        abort_unless($this->weekHasEnded($plan), 403, 'The week has not ended yet.');

        $plan->update(['status' => MealPlanStatus::Completed]);
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

        // Choosing a meal for a skipped slot ends the skip.
        $skipKey = $this->pickerDate.'|'.$slot->value;

        if ($plan->skippedSlots()->contains($skipKey)) {
            $plan->saveSkippedSlots($plan->skippedSlots()->reject(fn (string $key) => $key === $skipKey));
        }

        $this->moveToNextEmptyCell($plan);
    }

    /**
     * Open a past meal of a locked or completed week in the tray, where it
     * is logged. Read-only: nothing about the plan changes.
     */
    public function openMeal(string $date, string $slot): void
    {
        $plan = $this->plan();

        abort_unless($plan !== null, 404);
        abort_unless(in_array($plan->status, [MealPlanStatus::Locked, MealPlanStatus::Completed], true), 403, 'Only locked weeks are logged.');
        abort_unless($this->weekDates($plan)->contains($date), 422);
        abort_unless(Carbon::parse($date)->lte(today()), 403, 'That day has not happened yet.');
        abort_unless($plan->plannedMeals()->whereDate('date', $date)->where('slot', MealSlot::from($slot))->exists(), 404);

        $this->pickerDate = $date;
        $this->pickerSlot = $slot;
        $this->pickerSearch = '';
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
        $plan = $this->plan()?->load('plannedMeals.recipe', 'plannedMeals.logs');

        $meals = $plan
            ? $plan->plannedMeals->keyBy(fn (PlannedMeal $meal) => $meal->date->toDateString().'|'.$meal->slot->value)
            : collect();

        $pickerRecipes = $this->pickerRecipes();

        return view('livewire.plan-builder', [
            'plan' => $plan,
            'skipped' => $plan ? $plan->skippedSlots()->flip() : collect(),
            'cuisines' => [],
            'quickNightLimit' => app(HouseholdPreferences::class)->quickNightLimit(),
            'plans' => MealPlan::query()->orderByDesc('week_start_date')->get(),
            'days' => $plan ? $this->weekDates($plan)->map(fn (string $date) => Carbon::parse($date)) : collect(),
            'slots' => MealSlot::cases(),
            'meals' => $meals,
            'editable' => $plan?->status === MealPlanStatus::Draft,
            // Week-locking read-only rule explicitly permits logging: quick-log
            // controls appear exactly when the plan is locked or completed.
            'loggable' => $plan !== null
                && in_array($plan->status, [MealPlanStatus::Locked, MealPlanStatus::Completed], true),
            // Taste-profile hint for auto-fill; only computed while a draft
            // is on screen (short-circuits away on locked/completed weeks).
            'breakfastsOftenSkipped' => $plan?->status === MealPlanStatus::Draft
                && isset(app(ComputeTasteProfile::class)->handle()['slotPatterns'][MealSlot::Breakfast->value]),
            'completable' => $plan !== null
                && $plan->status === MealPlanStatus::Locked
                && $this->weekHasEnded($plan),
            'pickerRecipes' => $pickerRecipes,
            // How many cells each recipe already fills this week, for the tray tiles.
            'usesThisWeek' => $plan ? $plan->plannedMeals->countBy('recipe_id') : collect(),
            'missingTools' => app(MissingKitchenTools::class)->forMany($pickerRecipes),
            // Marked, not hidden: the household may still pick one for guests.
            'avoidedIngredients' => app(HouseholdPreferences::class)->avoidedInRecipes($pickerRecipes),
        ]);
    }

    /**
     * After a pick the tray moves to the next empty cell, reading the grid
     * row by row (each meal across the week) and wrapping to the top; it
     * closes when the week is full.
     */
    private function moveToNextEmptyCell(MealPlan $plan): void
    {
        $filled = $plan->plannedMeals()->get()
            ->map(fn (PlannedMeal $meal) => $meal->date->toDateString().'|'.$meal->slot->value)
            ->flip();

        $cells = collect(MealSlot::cases())
            ->flatMap(fn (MealSlot $slot) => $this->weekDates($plan)->map(fn (string $date) => $date.'|'.$slot->value));

        $from = $cells->search($this->pickerDate.'|'.$this->pickerSlot);
        $next = $cells->slice($from + 1)->concat($cells->take($from))
            ->first(fn (string $cell) => ! $filled->has($cell));

        if ($next === null) {
            $this->closePicker();

            return;
        }

        [$this->pickerDate, $this->pickerSlot] = explode('|', $next);
        $this->pickerSearch = '';
    }

    private function weekHasEnded(MealPlan $plan): bool
    {
        // The plan covers Mon–Fri, but "the week" runs through Sunday.
        return $plan->week_start_date->copy()->addDays(6)->endOfDay()->isPast();
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
        // A locked week's tray shows the meal to log, not recipes to pick.
        if ($this->pickerSlot === null || $this->plan()?->status !== MealPlanStatus::Draft) {
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
