<?php

namespace App\Actions\Planning;

use App\Enums\RecipeStatus;
use App\Models\MealLog;
use App\Models\Recipe;
use Illuminate\Support\Collection;

/**
 * Distills meal logs + recipe approvals/rejections into a structured taste
 * profile. Consumed by AutoFillWeek (scoring boosts, step 23) and serialized
 * into discovery prompts (step 24).
 */
class ComputeTasteProfile
{
    /** A cuisine/tag needs at least this many data points to count. */
    private const int MIN_DATA_POINTS = 2;

    /** Approving a recipe counts as this pseudo-rating data point. */
    private const float APPROVED_RATING = 4.0;

    /** Rejecting a recipe counts as this pseudo-rating data point. */
    private const float REJECTED_RATING = 1.5;

    /** Cuisines/tags below this average aren't "favored" — leave them out. */
    private const float FAVORED_MIN_AVG = 3.5;

    /** Top-N cap for cuisines and tags. */
    private const int TOP_LIMIT = 5;

    /** Cap for favored/avoided ingredient lists. */
    private const int INGREDIENT_LIMIT = 8;

    /** A meal log with this rating or higher (and eaten) is a HIGH signal. */
    private const int HIGH_RATING = 4;

    /** A meal log with this rating or lower — or uneaten — is a LOW signal. */
    private const int LOW_RATING = 2;

    /** An ingredient is favored/avoided when one side's rate is 2x the other's. */
    private const float DISPROPORTION_RATIO = 2.0;

    /** A slot skipped in more than half its logged opportunities is a pattern. */
    private const float SKIP_THRESHOLD = 0.5;

    /**
     * @return array{
     *     cuisines: array<string, float>,
     *     tags: array<string, float>,
     *     favoredIngredients: list<string>,
     *     avoidedIngredients: list<string>,
     *     slotPatterns: array<string, float>,
     * }
     */
    public function handle(): array
    {
        $logs = MealLog::query()->with('plannedMeal.recipe.ingredients')->get();

        $verdicts = Recipe::query()
            ->whereIn('status', [RecipeStatus::Approved, RecipeStatus::Rejected])
            ->get();

        [$favored, $avoided] = $this->ingredientSignals($logs);

        return [
            'cuisines' => $this->topByAverage($this->points($logs, $verdicts, 'cuisines')),
            'tags' => $this->topByAverage($this->points($logs, $verdicts, 'tags')),
            'favoredIngredients' => $favored,
            'avoidedIngredients' => $avoided,
            'slotPatterns' => $this->slotPatterns($logs),
        ];
    }

    /**
     * Rating data points per cuisine or tag: real meal-log ratings plus
     * approval/rejection verdicts mapped onto the same 1-5 scale.
     *
     * @param  Collection<int, MealLog>  $logs
     * @param  Collection<int, Recipe>  $verdicts
     * @return array<string, list<float>>
     */
    private function points(Collection $logs, Collection $verdicts, string $dimension): array
    {
        $points = [];

        $add = function (Recipe $recipe, float $value) use (&$points, $dimension): void {
            $keys = $dimension === 'cuisines'
                ? [(string) $recipe->cuisine]
                : ($recipe->tags ?? []);

            foreach ($keys as $key) {
                $key = mb_strtolower(trim((string) $key));

                if ($key !== '') {
                    $points[$key][] = $value;
                }
            }
        };

        foreach ($logs as $log) {
            if ($log->rating !== null) {
                $add($log->plannedMeal->recipe, (float) $log->rating);
            }
        }

        foreach ($verdicts as $recipe) {
            $add($recipe, $recipe->status === RecipeStatus::Approved
                ? self::APPROVED_RATING
                : self::REJECTED_RATING);
        }

        return $points;
    }

    /**
     * @param  array<string, list<float>>  $points
     * @return array<string, float>
     */
    private function topByAverage(array $points): array
    {
        return collect($points)
            ->filter(fn (array $values) => count($values) >= self::MIN_DATA_POINTS)
            ->map(fn (array $values) => round(array_sum($values) / count($values), 2))
            ->filter(fn (float $average) => $average >= self::FAVORED_MIN_AVG)
            ->sortDesc()
            ->take(self::TOP_LIMIT)
            ->all();
    }

    /**
     * Ingredients disproportionately present in high-rated meals (favored)
     * or in low-rated/uneaten meals (avoided). Presence is counted once per
     * logged meal; rates are normalized by each side's meal count so a busy
     * side doesn't drown the other.
     *
     * @param  Collection<int, MealLog>  $logs
     * @return array{0: list<string>, 1: list<string>}
     */
    private function ingredientSignals(Collection $logs): array
    {
        $high = $logs->filter(fn (MealLog $log) => $log->ate_it
            && $log->rating !== null
            && $log->rating >= self::HIGH_RATING);

        $low = $logs->filter(fn (MealLog $log) => ! $log->ate_it
            || ($log->rating !== null && $log->rating <= self::LOW_RATING));

        $counts = fn (Collection $side): Collection => $side
            ->flatMap(fn (MealLog $log) => $log->plannedMeal->recipe->ingredients->pluck('name')->unique())
            ->countBy();

        $highCounts = $counts($high);
        $lowCounts = $counts($low);

        $pick = function (Collection $primary, int $primaryMeals, Collection $other, int $otherMeals): array {
            return $primary
                ->filter(function (int $count, string $name) use ($primaryMeals, $other, $otherMeals) {
                    $primaryRate = $count / max(1, $primaryMeals);
                    $otherRate = ($other[$name] ?? 0) / max(1, $otherMeals);

                    return $count >= self::MIN_DATA_POINTS
                        && $primaryRate >= self::DISPROPORTION_RATIO * $otherRate;
                })
                ->sortDesc()
                ->take(self::INGREDIENT_LIMIT)
                ->keys()
                ->all();
        };

        return [
            $pick($highCounts, $high->count(), $lowCounts, $low->count()),
            $pick($lowCounts, $low->count(), $highCounts, $high->count()),
        ];
    }

    /**
     * Slots skipped (ate_it = false) in more than half of at least
     * MIN_DATA_POINTS logged opportunities, as slot => skip rate.
     *
     * @param  Collection<int, MealLog>  $logs
     * @return array<string, float>
     */
    private function slotPatterns(Collection $logs): array
    {
        return $logs
            ->groupBy(fn (MealLog $log) => $log->plannedMeal->slot->value)
            ->filter(fn (Collection $group) => $group->count() >= self::MIN_DATA_POINTS)
            ->map(fn (Collection $group) => round(
                $group->filter(fn (MealLog $log) => ! $log->ate_it)->count() / $group->count(),
                2,
            ))
            ->filter(fn (float $rate) => $rate > self::SKIP_THRESHOLD)
            ->all();
    }
}
