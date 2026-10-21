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
use App\Support\SlotKey;
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
     * approved recipes, leaving the week check-in's skipped slots empty, and
     * report the created planned meals and why any slot the household asked
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

        // A slot with a meal, and a skipped slot (eating out, away), is never filled.
        // Recipes already on the plan count toward the no-repeat rule.
        $state = new FillState(
            $existing->mapWithKeys(fn (PlannedMeal $meal) => [SlotKey::make($meal->date, $meal->slot) => true])
                ->toBase()->merge($plan->skippedSlots()->flip()),
            $existing->countBy('recipe_id')->all(),
        );

        $quickNights = $plan->quickNights()->flip();
        $quickLimit = $this->preferences->quickNightLimit();

        $craving = $plan->craving();

        if ($craving !== null) {
            $this->placeCraving($plan, $craving, $existing, $pools[MealSlot::Dinner->value], $weekStart, $quickNights, $quickLimit, $state);
        }

        for ($day = 0; $day < self::WEEKDAYS; $day++) {
            $date = $weekStart->copy()->addDays($day)->toDateString();

            foreach (MealSlot::cases() as $slot) {
                if ($state->filled->has(SlotKey::make($date, $slot))) {
                    continue;
                }

                $pool = $pools[$slot->value];

                // A quick night limits dinner only, to recipes whose total minutes
                // are known and within the quick-night limit.
                if ($slot === MealSlot::Dinner && $quickNights->has($date)) {
                    $pool = $pool->filter(fn (Recipe $recipe) => $recipe->fitsQuickNight($quickLimit))->values();

                    if ($pool->isEmpty()) {
                        $state->unfilled[] = "No recipe under {$quickLimit} min for ".Carbon::parse($date)->format('D').' dinner';
                    }
                }

                $pick = $this->pick($pool, $state->useCounts);

                if ($pick === null) {
                    continue; // Empty pool — nothing eligible for this slot.
                }

                $state->place($plan, $pick, $date, $slot);
            }
        }

        return new AutoFillResult($state->created, $state->unfilled);
    }

    /**
     * Place the craving's guaranteed dinners before normal filling: recipes of
     * the craved cuisine, best score first, none repeated, on empty dinners
     * that are not quick nights (earliest first) and only then on quick nights,
     * where the recipe must fit the quick-night limit. A shortfall becomes a
     * sentence in the state's unfilled list, naming its cause: too few
     * eligible recipes in the library, too few free dinners on the week, or both.
     *
     * @param  Collection<int, PlannedMeal>  $existing
     * @param  Collection<int, Recipe>  $dinnerPool  sorted best score first
     * @param  Collection<string, int>  $quickNights  dates flipped to keys
     */
    private function placeCraving(
        MealPlan $plan,
        string $craving,
        Collection $existing,
        Collection $dinnerPool,
        Carbon $weekStart,
        Collection $quickNights,
        int $quickLimit,
        FillState $state,
    ): void {
        $matches = fn (?string $cuisine) => Recipe::cuisineKey($cuisine) === Recipe::cuisineKey($craving);

        $have = $existing
            ->load('recipe')
            ->filter(fn (PlannedMeal $meal) => $meal->slot === MealSlot::Dinner && $matches($meal->recipe->cuisine))
            ->count();

        $needed = max(0, self::CRAVING_DINNERS - $have);

        $cravingPool = $dinnerPool
            ->filter(fn (Recipe $recipe) => $matches($recipe->cuisine) && ! isset($state->useCounts[$recipe->id]))
            ->values();

        $dates = collect(range(0, self::WEEKDAYS - 1))
            ->map(fn (int $day) => $weekStart->copy()->addDays($day)->toDateString())
            ->filter(fn (string $date) => ! $state->filled->has(SlotKey::make($date, MealSlot::Dinner)))
            ->sortBy(fn (string $date) => $quickNights->has($date))
            ->values();

        $libraryShort = $cravingPool->count() < $needed;
        $freeShort = $dates->count() < $needed;

        foreach ($dates as $date) {
            if ($have >= self::CRAVING_DINNERS) {
                break;
            }

            $pool = $quickNights->has($date)
                ? $cravingPool->filter(fn (Recipe $recipe) => $recipe->fitsQuickNight($quickLimit))
                : $cravingPool;
            $pick = $pool->first();

            if ($pick === null) {
                continue;
            }

            $cravingPool = $cravingPool->reject(fn (Recipe $recipe) => $recipe->id === $pick->id)->values();
            $state->place($plan, $pick, $date, MealSlot::Dinner);
            $have++;
        }

        if ($have >= self::CRAVING_DINNERS) {
            return;
        }

        // Too few free dinners alone is not the library's fault; anything else
        // (too few recipes, or none that fit a quick night) is.
        if ($libraryShort || ! $freeShort) {
            $state->unfilled[] = 'Found only '.$have.' of '.self::CRAVING_DINNERS." {$craving} dinners for your craving";
        }

        if ($freeShort) {
            $free = $dates->count();
            $state->unfilled[] = $free === 0
                ? "No free dinner left for your {$craving} craving"
                : "Only {$free} free dinner for your {$craving} craving";
        }
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
