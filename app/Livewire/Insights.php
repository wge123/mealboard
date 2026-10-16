<?php

namespace App\Livewire;

use App\Enums\MealSlot;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\MealLog;
use App\Models\Recipe;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Read-only insights report: headline figures, ate/skipped rates by meal and
 * weekday, top-rated and most-skipped recipes, cuisines, and how many
 * discovered recipes were kept. All aggregates computed PHP-side (sqlite's
 * bound-numeric aggregate comparisons are unreliable).
 */
#[Layout('layouts.app')]
#[Title('Insights')]
class Insights extends Component
{
    private const int LIST_LIMIT = 6;

    public function render(): View
    {
        $logs = MealLog::query()->with('plannedMeal.recipe')->get();

        $ate = $logs->filter(fn (MealLog $log) => $log->ate_it)->count();
        $rated = $logs->filter(fn (MealLog $log) => $log->rating !== null);

        return view('livewire.insights', [
            'totals' => [
                'logs' => $logs->count(),
                'ate' => $ate,
                'skipped' => $logs->count() - $ate,
                'ateRate' => $logs->isEmpty() ? null : (int) round($ate / $logs->count() * 100),
                'rated' => $rated->count(),
                'avgRating' => $rated->isEmpty() ? null : round($rated->avg('rating'), 1),
            ],
            'coverage' => $this->coverage($logs),
            'slotRates' => $this->ateRates(
                $logs,
                fn (MealLog $log) => $log->plannedMeal->slot->value,
                array_column(MealSlot::cases(), 'value'),
            ),
            'weekdayRates' => $this->ateRates(
                $logs,
                fn (MealLog $log) => $log->plannedMeal->date->format('l'),
                ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            ),
            'topRecipes' => $this->topRecipes($logs),
            'skippedRecipes' => $this->skippedRecipes($logs),
            'cuisines' => $this->cuisines($logs),
            'discovered' => $this->discoveredVerdicts(),
        ]);
    }

    /**
     * The planned weeks the logs fall in (Monday start): how many, and the
     * first Monday to the last Sunday. Null when nothing is logged.
     *
     * @param  Collection<int, MealLog>  $logs
     * @return array{weeks: int, from: CarbonInterface, to: CarbonInterface}|null
     */
    private function coverage(Collection $logs): ?array
    {
        $weeks = $logs
            ->map(fn (MealLog $log) => $log->plannedMeal->date->copy()->startOfWeek()->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($weeks->isEmpty()) {
            return null;
        }

        return [
            'weeks' => $weeks->count(),
            'from' => CarbonImmutable::parse($weeks->first()),
            'to' => CarbonImmutable::parse($weeks->last())->addDays(6),
        ];
    }

    /**
     * Ate vs skipped counts and ate-rate percent, grouped by the given key,
     * in a fixed label order (labels without logs are omitted).
     *
     * @param  Collection<int, MealLog>  $logs
     * @param  list<string>  $order
     * @return array<string, array{ate: int, skipped: int, rate: int}>
     */
    private function ateRates(Collection $logs, callable $key, array $order): array
    {
        $groups = $logs->groupBy($key);

        $rows = [];

        foreach ($order as $label) {
            $group = $groups->get($label);

            if ($group === null) {
                continue;
            }

            $ate = $group->filter(fn (MealLog $log) => $log->ate_it)->count();

            $rows[$label] = [
                'ate' => $ate,
                'skipped' => $group->count() - $ate,
                'rate' => (int) round($ate / $group->count() * 100),
            ];
        }

        return $rows;
    }

    /**
     * Top recipes by average rating (more rated meals, then title, on a tie),
     * at least one rated log each.
     *
     * @param  Collection<int, MealLog>  $logs
     * @return Collection<int, array{id: int, title: string, avg: float, count: int}>
     */
    private function topRecipes(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (MealLog $log) => $log->rating !== null)
            ->groupBy(fn (MealLog $log) => $log->plannedMeal->recipe_id)
            ->map(fn (Collection $group) => [
                'id' => $group->first()->plannedMeal->recipe_id,
                'title' => $group->first()->plannedMeal->recipe->title,
                'avg' => round($group->avg('rating'), 1),
                'count' => $group->count(),
            ])
            ->sort(fn (array $a, array $b) => [$b['avg'], $b['count'], $a['title']] <=> [$a['avg'], $a['count'], $b['title']])
            ->take(self::LIST_LIMIT)
            ->values();
    }

    /**
     * Recipes skipped at least once, most skipped first (fewer plans first on
     * a tie, so a recipe skipped every time outranks one skipped now and then).
     *
     * @param  Collection<int, MealLog>  $logs
     * @return Collection<int, array{id: int, title: string, skipped: int, planned: int}>
     */
    private function skippedRecipes(Collection $logs): Collection
    {
        return $logs
            ->groupBy(fn (MealLog $log) => $log->plannedMeal->recipe_id)
            ->map(fn (Collection $group) => [
                'id' => $group->first()->plannedMeal->recipe_id,
                'title' => $group->first()->plannedMeal->recipe->title,
                'skipped' => $group->filter(fn (MealLog $log) => ! $log->ate_it)->count(),
                'planned' => $group->count(),
            ])
            ->filter(fn (array $row) => $row['skipped'] > 0)
            ->sort(fn (array $a, array $b) => [$b['skipped'], $a['planned'], $a['title']] <=> [$a['skipped'], $b['planned'], $b['title']])
            ->take(self::LIST_LIMIT)
            ->values();
    }

    /**
     * Meals per cuisine (case-merged; a recipe with none counts as "No
     * cuisine") with eaten rate and average rating, most meals first.
     *
     * @param  Collection<int, MealLog>  $logs
     * @return Collection<int, array{cuisine: string, meals: int, rate: int, avg: ?float}>
     */
    private function cuisines(Collection $logs): Collection
    {
        return $logs
            ->groupBy(fn (MealLog $log) => ucfirst(strtolower(trim((string) $log->plannedMeal->recipe->cuisine))) ?: 'No cuisine')
            ->map(function (Collection $group, string $cuisine) {
                $rated = $group->filter(fn (MealLog $log) => $log->rating !== null);

                return [
                    'cuisine' => $cuisine,
                    'meals' => $group->count(),
                    'rate' => (int) round($group->filter(fn (MealLog $log) => $log->ate_it)->count() / $group->count() * 100),
                    'avg' => $rated->isEmpty() ? null : round($rated->avg('rating'), 1),
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['meals'], $a['cuisine']] <=> [$a['meals'], $b['cuisine']])
            ->values();
    }

    /**
     * Verdicts on discovered recipes: kept (approved) vs passed (rejected),
     * and the kept percent. The rate is null until a discovered recipe has a verdict.
     *
     * @return array{approved: int, rejected: int, keptRate: ?int}
     */
    private function discoveredVerdicts(): array
    {
        $verdicts = Recipe::query()
            ->where('source', RecipeSource::Discovered)
            ->whereIn('status', [RecipeStatus::Approved, RecipeStatus::Rejected])
            ->get();

        $approved = $verdicts->filter(fn (Recipe $recipe) => $recipe->status === RecipeStatus::Approved)->count();

        return [
            'approved' => $approved,
            'rejected' => $verdicts->count() - $approved,
            'keptRate' => $verdicts->isEmpty() ? null : (int) round($approved / $verdicts->count() * 100),
        ];
    }
}
