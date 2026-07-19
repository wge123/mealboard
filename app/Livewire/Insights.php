<?php

namespace App\Livewire;

use App\Enums\MealSlot;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\MealLog;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Step 28 — read-only insights: ate/skipped rates, top-rated recipes, and
 * the discovery rejection rate. All aggregates computed PHP-side (sqlite's
 * bound-numeric aggregate comparisons are unreliable).
 */
#[Layout('layouts.app')]
#[Title('Insights')]
class Insights extends Component
{
    private const int TOP_RECIPES_LIMIT = 10;

    public function render(): View
    {
        $logs = MealLog::query()->with('plannedMeal.recipe')->get();

        return view('livewire.insights', [
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
            'discovered' => $this->discoveredRejection(),
        ]);
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
     * Top recipes by average rating, at least one rated log each.
     *
     * @param  Collection<int, MealLog>  $logs
     * @return Collection<int, array{title: string, avg: float, count: int}>
     */
    private function topRecipes(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (MealLog $log) => $log->rating !== null)
            ->groupBy(fn (MealLog $log) => $log->plannedMeal->recipe_id)
            ->map(fn (Collection $group) => [
                'title' => $group->first()->plannedMeal->recipe->title,
                'avg' => round($group->avg('rating'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('avg')
            ->take(self::TOP_RECIPES_LIMIT)
            ->values();
    }

    /**
     * Rejection rate of discovered recipes: rejected / (approved + rejected).
     * Rate is null until a discovered recipe has a verdict.
     *
     * @return array{approved: int, rejected: int, rate: ?int}
     */
    private function discoveredRejection(): array
    {
        $verdicts = Recipe::query()
            ->where('source', RecipeSource::Discovered)
            ->whereIn('status', [RecipeStatus::Approved, RecipeStatus::Rejected])
            ->get();

        $approved = $verdicts->filter(fn (Recipe $recipe) => $recipe->status === RecipeStatus::Approved)->count();
        $rejected = $verdicts->count() - $approved;

        return [
            'approved' => $approved,
            'rejected' => $rejected,
            'rate' => $verdicts->isEmpty() ? null : (int) round($rejected / $verdicts->count() * 100),
        ];
    }
}
