<?php

namespace App\Actions\Planning;

use App\Models\MealPlan;
use App\Support\CleanIngredientKeywords;
use RuntimeException;

/**
 * Pure assembly for walmart:push-list: resolve the target week (refusing a
 * stale latest locked week), build its shopping list, drop checked items,
 * and reduce each remaining line to its cleaned Walmart keywords (deduped —
 * two unit buckets of one ingredient should not push two identical list
 * entries). No browser anywhere near this.
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

        foreach ($this->buildShoppingList->handle($plan)['lines'] as $items) {
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

        $plan = MealPlan::latestLocked();

        if ($plan === null) {
            throw new RuntimeException('No locked week.');
        }

        // A stale week (GLOSSARY: locked, dates already past, nothing newer
        // locked) is almost never the one meant — stop rather than push last
        // month's groceries. Naming the week explicitly is the override.
        $weeksStale = $plan->weeksStale();

        if ($weeksStale > 0) {
            $week = $plan->week_start_date->toDateString();

            throw new RuntimeException(sprintf(
                'The latest locked week (%s) is %d week%s stale — lock a newer week, or pass --week=%s to push it anyway.',
                $week,
                $weeksStale,
                $weeksStale === 1 ? '' : 's',
                $week,
            ));
        }

        return $plan;
    }
}
