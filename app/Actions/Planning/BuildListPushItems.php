<?php

namespace App\Actions\Planning;

use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Support\CleanIngredientKeywords;
use RuntimeException;

/**
 * Pure assembly for walmart:push-list: resolve the target week, build its
 * shopping list, drop checked items, and reduce each remaining line to its
 * cleaned Walmart keywords (deduped — two unit buckets of one ingredient
 * should not push two identical list entries). No browser anywhere near this.
 */
class BuildListPushItems
{
    public function __construct(
        private BuildShoppingList $buildShoppingList,
        private CleanIngredientKeywords $cleanKeywords,
    ) {}

    /**
     * @return list<string> cleaned keywords, in store-flow list order
     */
    public function handle(?string $weekStart = null): array
    {
        $plan = $this->plan($weekStart);
        $checked = $plan->checked_items ?? [];

        $keywords = [];

        foreach ($this->buildShoppingList->handle($plan) as $items) {
            foreach ($items as $item) {
                if (in_array($item['name'].'|'.($item['unit'] ?? ''), $checked, true)) {
                    continue;
                }

                $clean = $this->cleanKeywords->handle($item['name']);

                if ($clean !== '' && ! in_array($clean, $keywords, true)) {
                    $keywords[] = $clean;
                }
            }
        }

        return $keywords;
    }

    private function plan(?string $weekStart): MealPlan
    {
        if ($weekStart !== null) {
            $plan = MealPlan::query()->whereDate('week_start_date', $weekStart)->first();

            if ($plan === null) {
                throw new RuntimeException("No meal plan for week starting {$weekStart}.");
            }

            return $plan;
        }

        $plan = MealPlan::query()
            ->where('status', MealPlanStatus::Locked)
            ->orderByDesc('week_start_date')
            ->first();

        if ($plan === null) {
            throw new RuntimeException('No locked week.');
        }

        return $plan;
    }
}
