<?php

namespace App\Actions\Planning;

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;
use Random\Randomizer;

class AutoFillWeek
{
    /** Mon–Fri. */
    private const int WEEKDAYS = 5;

    /** Neutral score for recipes without any ratings yet. */
    private const float UNRATED_SCORE = 2.5;

    /** Subtracted when the recipe was planned in the prior window. */
    private const float RECENT_PENALTY = 2.0;

    /** Look-back window (days before the plan's week start). */
    private const int RECENT_DAYS = 14;

    /** Jitter stays below any meaningful rating gap so it only breaks ties. */
    private const float JITTER_MAX = 0.25;

    public function __construct(private Randomizer $randomizer = new Randomizer) {}

    /**
     * Fill the plan's empty Mon–Fri breakfast/lunch/dinner slots with
     * approved recipes and return the created planned meals.
     *
     * @return Collection<int, PlannedMeal>
     */
    public function handle(MealPlan $plan): Collection
    {
        if ($plan->status !== MealPlanStatus::Draft) {
            throw new LogicException('Only draft plans can be auto-filled.');
        }

        $weekStart = $plan->week_start_date->copy()->startOfDay();

        $candidates = Recipe::query()
            ->where('status', RecipeStatus::Approved)
            ->orderBy('id')
            ->get();

        $scores = $this->scores($candidates, $weekStart);

        // One pool per slot: recipes whose meal_type matches the slot or is
        // 'any', best score first.
        $pools = [];
        foreach (MealSlot::cases() as $slot) {
            $pools[$slot->value] = $candidates
                ->filter(fn (Recipe $recipe) => $recipe->meal_type === MealType::Any
                    || $recipe->meal_type->value === $slot->value)
                ->sortByDesc(fn (Recipe $recipe) => $scores[$recipe->id])
                ->values();
        }

        $existing = $plan->plannedMeals()->get();

        $filled = $existing
            ->mapWithKeys(fn (PlannedMeal $meal) => [$meal->date->toDateString().'|'.$meal->slot->value => true]);

        // Recipes already on the plan count toward the no-repeat rule.
        $useCounts = $existing->countBy('recipe_id')->all();

        $created = collect();

        for ($day = 0; $day < self::WEEKDAYS; $day++) {
            $date = $weekStart->copy()->addDays($day)->toDateString();

            foreach (MealSlot::cases() as $slot) {
                if ($filled->has($date.'|'.$slot->value)) {
                    continue;
                }

                $pick = $this->pick($pools[$slot->value], $useCounts);

                if ($pick === null) {
                    continue; // Empty pool — nothing eligible for this slot.
                }

                $useCounts[$pick->id] = ($useCounts[$pick->id] ?? 0) + 1;

                $created->push($plan->plannedMeals()->create([
                    'recipe_id' => $pick->id,
                    'date' => $date,
                    'slot' => $slot,
                ]));
            }
        }

        return $created;
    }

    /**
     * Highest-scoring recipe not yet used this week; once the pool is
     * exhausted, fall back to the least-used one (best score first).
     *
     * @param  Collection<int, Recipe>  $pool  sorted best score first
     * @param  array<int, int>  $useCounts
     */
    private function pick(Collection $pool, array $useCounts): ?Recipe
    {
        if ($pool->isEmpty()) {
            return null;
        }

        $minUses = $pool->min(fn (Recipe $recipe) => $useCounts[$recipe->id] ?? 0);

        return $pool->first(fn (Recipe $recipe) => ($useCounts[$recipe->id] ?? 0) === $minUses);
    }

    /**
     * Score = avg rating (via meal logs) − recent-planning penalty + jitter.
     * Jitter is drawn once per recipe in id order, so a seeded Randomizer
     * makes the whole fill deterministic.
     *
     * @param  Collection<int, Recipe>  $candidates  ordered by id
     * @return array<int, float>
     */
    private function scores(Collection $candidates, Carbon $weekStart): array
    {
        // Averages are compared in PHP, not in SQL, so the sqlite TEXT-bound
        // numeric gotcha doesn't apply here.
        $avgRatings = MealLog::query()
            ->join('planned_meals', 'planned_meals.id', '=', 'meal_logs.planned_meal_id')
            ->whereNotNull('meal_logs.rating')
            ->groupBy('planned_meals.recipe_id')
            ->selectRaw('planned_meals.recipe_id as recipe_id, AVG(meal_logs.rating) as avg_rating')
            ->pluck('avg_rating', 'recipe_id');

        $recentIds = PlannedMeal::query()
            ->whereDate('date', '<', $weekStart->toDateString())
            ->whereDate('date', '>=', $weekStart->copy()->subDays(self::RECENT_DAYS)->toDateString())
            ->pluck('recipe_id')
            ->unique()
            ->flip();

        $scores = [];

        foreach ($candidates as $recipe) {
            $scores[$recipe->id] = (float) ($avgRatings[$recipe->id] ?? self::UNRATED_SCORE)
                - ($recentIds->has($recipe->id) ? self::RECENT_PENALTY : 0.0)
                + $this->randomizer->getFloat(0.0, self::JITTER_MAX);
        }

        return $scores;
    }
}
