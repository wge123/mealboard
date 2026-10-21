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
use App\Support\HouseholdPreferences;
use App\Support\MissingKitchenTools;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;
use Random\Randomizer;

class AutoFillWeek
{
    /** Mon–Fri. */
    private const int WEEKDAYS = 5;

    /** Dinners of the craved cuisine the fill guarantees. */
    private const int CRAVING_DINNERS = 2;

    /** Neutral score for recipes without any ratings yet. */
    private const float UNRATED_SCORE = 2.5;

    /** Subtracted when the recipe was planned in the prior window. */
    private const float RECENT_PENALTY = 2.0;

    /** Look-back window (days before the plan's week start). */
    private const int RECENT_DAYS = 14;

    /** Jitter stays below any meaningful rating gap so it only breaks ties. */
    private const float JITTER_MAX = 0.25;

    /** Added when the recipe's cuisine is profile-favored (beats jitter). */
    private const float CUISINE_BOOST = 0.5;

    /** Added when any recipe tag is profile-favored. */
    private const float TAG_BOOST = 0.25;

    /**
     * Per-total-minute penalty applied to breakfast candidates when the
     * profile shows breakfasts are often skipped: a 30-minute gap (0.6)
     * outweighs jitter (0.25), so faster breakfasts win deterministically.
     */
    private const float SKIP_MINUTES_PENALTY = 0.02;

    public function __construct(
        private Randomizer $randomizer = new Randomizer,
        private ComputeTasteProfile $tasteProfile = new ComputeTasteProfile,
        private MissingKitchenTools $missingTools = new MissingKitchenTools,
        private HouseholdPreferences $preferences = new HouseholdPreferences,
    ) {}

    /**
     * Fill the plan's empty Mon–Fri breakfast/lunch/dinner slots with
     * approved recipes, leaving the week check-in's skipped slots empty,
     * and return the created planned meals.
     *
     * @return Collection<int, PlannedMeal>
     */
    public function handle(MealPlan $plan): Collection
    {
        return $this->fill($plan)->meals;
    }

    /**
     * Fill as handle() does, and also report why any slot the household asked
     * about stayed empty. A reason is a plain sentence in the result, never an
     * exception: a new answer that can leave a slot empty adds its sentence to
     * the same list.
     */
    public function fill(MealPlan $plan): AutoFillResult
    {
        if ($plan->status !== MealPlanStatus::Draft) {
            throw new LogicException('Only draft plans can be auto-filled.');
        }

        $weekStart = $plan->week_start_date->copy()->startOfDay();

        $candidates = Recipe::query()
            ->where('status', RecipeStatus::Approved)
            ->orderBy('id')
            ->get();

        // A recipe with a kitchen tool the household lacks, or with an
        // ingredient it avoids, is never auto-planned.
        $flagged = $this->missingTools->flaggedIds($candidates)->flip();
        $avoided = $this->preferences->avoidedInRecipes($candidates);
        $candidates = $candidates
            ->reject(fn (Recipe $recipe) => $flagged->has($recipe->id) || $avoided[$recipe->id] !== [])
            ->values();

        $profile = $this->tasteProfile->handle();

        $scores = $this->scores($candidates, $weekStart, $profile);

        // Breakfast-skip pattern: prefer lower total-minutes breakfasts.
        $breakfastSkipped = isset($profile['slotPatterns'][MealSlot::Breakfast->value]);

        // One pool per slot: recipes whose meal_type matches the slot or is
        // 'any', best score first.
        $pools = [];
        foreach (MealSlot::cases() as $slot) {
            $penalized = $breakfastSkipped && $slot === MealSlot::Breakfast;

            $pools[$slot->value] = $candidates
                ->filter(fn (Recipe $recipe) => $recipe->meal_type === MealType::Any
                    || $recipe->meal_type->value === $slot->value)
                ->sortByDesc(fn (Recipe $recipe) => $scores[$recipe->id]
                    - ($penalized ? ($recipe->prep_minutes + $recipe->cook_minutes) * self::SKIP_MINUTES_PENALTY : 0.0))
                ->values();
        }

        $existing = $plan->plannedMeals()->get();

        $filled = $existing
            ->mapWithKeys(fn (PlannedMeal $meal) => [$meal->date->toDateString().'|'.$meal->slot->value => true]);

        // A skipped slot (eating out, away) is never filled.
        $filled = $filled->toBase()->merge($plan->skippedSlots()->flip());

        // Recipes already on the plan count toward the no-repeat rule.
        $useCounts = $existing->countBy('recipe_id')->all();

        $created = collect();

        // Slots left empty for a reason the household can act on, in week order.
        $unfilled = [];

        $quickNights = $plan->quickNights()->flip();
        $quickLimit = $this->preferences->quickNightLimit();

        $quickNights = $plan->quickNights()->flip();
        $quickLimit = $this->preferences->quickNightLimit();

        $craving = trim((string) $plan->craving());

        if ($craving !== '') {
            $this->placeCraving(
                $plan, $craving, $existing, $pools[MealSlot::Dinner->value], $weekStart,
                $filled, $quickNights, $quickLimit, $useCounts, $created, $unfilled,
            );
        }

        for ($day = 0; $day < self::WEEKDAYS; $day++) {
            $date = $weekStart->copy()->addDays($day)->toDateString();

            foreach (MealSlot::cases() as $slot) {
                if ($filled->has($date.'|'.$slot->value)) {
                    continue;
                }

                $pool = $pools[$slot->value];

                // A quick night limits dinner only, to recipes whose total minutes
                // are known and within the quick-night limit.
                if ($slot === MealSlot::Dinner && $quickNights->has($date)) {
                    $pool = $this->withinQuickLimit($pool, $quickLimit);

                    if ($pool->isEmpty()) {
                        $unfilled[] = "No recipe under {$quickLimit} min for ".Carbon::parse($date)->format('D').' dinner';
                    }
                }

                $pick = $this->pick($pool, $useCounts);

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

        return new AutoFillResult($created, $unfilled);
    }

    /**
     * Place the craving's guaranteed dinners before normal filling: recipes of
     * the craved cuisine, best score first, none repeated, on empty dinners
     * that are not quick nights (earliest first) and only then on quick nights,
     * where the recipe must fit the quick-night limit. A shortfall becomes a
     * sentence in $unfilled.
     *
     * @param  Collection<int, PlannedMeal>  $existing
     * @param  Collection<int, Recipe>  $dinnerPool  sorted best score first
     * @param  Collection<string, mixed>  $filled  slot keys taken, updated here
     * @param  Collection<string, int>  $quickNights  dates flipped to keys
     * @param  array<int, int>  $useCounts  updated here
     * @param  Collection<int, PlannedMeal>  $created  appended to here
     * @param  list<string>  $unfilled  appended to here
     */
    private function placeCraving(
        MealPlan $plan,
        string $craving,
        Collection $existing,
        Collection $dinnerPool,
        Carbon $weekStart,
        Collection $filled,
        Collection $quickNights,
        int $quickLimit,
        array &$useCounts,
        Collection $created,
        array &$unfilled,
    ): void {
        $matches = fn (?string $cuisine) => mb_strtolower(trim((string) $cuisine)) === mb_strtolower($craving);

        $have = $existing
            ->load('recipe')
            ->filter(fn (PlannedMeal $meal) => $meal->slot === MealSlot::Dinner && $matches($meal->recipe?->cuisine))
            ->count();

        $cravingPool = $dinnerPool
            ->filter(fn (Recipe $recipe) => $matches($recipe->cuisine) && ! isset($useCounts[$recipe->id]))
            ->values();

        $dates = collect(range(0, self::WEEKDAYS - 1))
            ->map(fn (int $day) => $weekStart->copy()->addDays($day)->toDateString())
            ->filter(fn (string $date) => ! $filled->has($date.'|'.MealSlot::Dinner->value))
            ->sortBy(fn (string $date) => $quickNights->has($date))
            ->values();

        foreach ($dates as $date) {
            if ($have >= self::CRAVING_DINNERS) {
                break;
            }

            $pool = $quickNights->has($date) ? $this->withinQuickLimit($cravingPool, $quickLimit) : $cravingPool;
            $pick = $pool->first();

            if ($pick === null) {
                continue;
            }

            $cravingPool = $cravingPool->reject(fn (Recipe $recipe) => $recipe->id === $pick->id)->values();
            $useCounts[$pick->id] = 1;
            $filled->put($date.'|'.MealSlot::Dinner->value, true);
            $have++;

            $created->push($plan->plannedMeals()->create([
                'recipe_id' => $pick->id,
                'date' => $date,
                'slot' => MealSlot::Dinner,
            ]));
        }

        if ($have < self::CRAVING_DINNERS) {
            $unfilled[] = 'Found only '.$have.' of '.self::CRAVING_DINNERS." {$craving} dinners for your craving";
        }
    }

    /**
     * Recipes whose total minutes are known and within the quick-night limit.
     *
     * @param  Collection<int, Recipe>  $pool
     * @return Collection<int, Recipe>
     */
    private function withinQuickLimit(Collection $pool, int $quickLimit): Collection
    {
        return $pool->filter(fn (Recipe $recipe) => ($minutes = $recipe->prep_minutes + $recipe->cook_minutes) > 0 && $minutes <= $quickLimit)->values();
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
     * Score = avg rating (via meal logs) − recent-planning penalty
     * + taste-profile boosts + jitter. Jitter is drawn once per recipe in id
     * order, so a seeded Randomizer makes the whole fill deterministic.
     *
     * @param  Collection<int, Recipe>  $candidates  ordered by id
     * @param  array{cuisines: array<string, float>, tags: array<string, float>}  $profile
     * @return array<int, float>
     */
    private function scores(Collection $candidates, Carbon $weekStart, array $profile): array
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
                + $this->profileBoost($recipe, $profile)
                + $this->randomizer->getFloat(0.0, self::JITTER_MAX);
        }

        return $scores;
    }

    /**
     * @param  array{cuisines: array<string, float>, tags: array<string, float>}  $profile
     */
    private function profileBoost(Recipe $recipe, array $profile): float
    {
        $boost = 0.0;

        $cuisine = mb_strtolower(trim((string) $recipe->cuisine));

        if ($cuisine !== '' && array_key_exists($cuisine, $profile['cuisines'])) {
            $boost += self::CUISINE_BOOST;
        }

        $tags = array_map(fn (string $tag) => mb_strtolower(trim($tag)), $recipe->tags ?? []);

        if (array_intersect($tags, array_keys($profile['tags'])) !== []) {
            $boost += self::TAG_BOOST;
        }

        return $boost;
    }
}
