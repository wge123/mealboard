<?php

namespace App\Actions\Planning;

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Support\GitHubClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * DECISIONS.md #7 (push half) — mirrors locked meal plans into the vault
 * repo's meals/ folder as markdown via the GitHub contents API:
 * meals/week-YYYY-WW.md (Mon–Fri grid) and meals/today.md (today's meals
 * plus tomorrow's prep-ahead lines).
 */
class PublishMealsMarkdown
{
    public function __construct(private GitHubClient $github) {}

    /**
     * Publish a plan's week grid, plus today.md when the plan covers today.
     * Called on lock (LockWeek).
     */
    public function handle(MealPlan $plan): void
    {
        $plan->load('plannedMeals.recipe');

        $this->push($this->weekPath($plan), $this->weekMarkdown($plan));

        if ($this->coversToday($plan)) {
            $this->push('meals/today.md', $this->todayMarkdown($plan));
        }
    }

    /**
     * Nightly today.md refresh for the current locked week. Returns false —
     * quietly, with a log line — when no locked week covers today.
     */
    public function publishToday(): bool
    {
        $plan = MealPlan::query()
            ->where('status', MealPlanStatus::Locked)
            ->whereDate('week_start_date', today()->startOfWeek(CarbonInterface::MONDAY)->toDateString())
            ->first();

        if ($plan === null || ! $this->coversToday($plan)) {
            Log::info('meals/today.md not published — no locked week covers today.');

            return false;
        }

        $plan->load('plannedMeals.recipe');

        $this->push('meals/today.md', $this->todayMarkdown($plan));

        return true;
    }

    /**
     * Vault write gate — meal publishing may only ever touch meals/.
     */
    public function push(string $path, string $markdown): void
    {
        if (! str_starts_with($path, 'meals/')) {
            throw new InvalidArgumentException("Refusing to publish outside meals/: {$path}");
        }

        $this->github->putFile(
            config('mealboard.brain_repo'),
            $path,
            $markdown,
            "mealboard: publish {$path}",
        );
    }

    private function weekPath(MealPlan $plan): string
    {
        // ISO year-week (o-W): the plan's Monday pins the week unambiguously.
        return 'meals/week-'.$plan->week_start_date->format('o-W').'.md';
    }

    private function weekMarkdown(MealPlan $plan): string
    {
        $lines = [
            '# Week of '.$plan->week_start_date->toDateString(),
            '',
            '| Day | Breakfast | Lunch | Dinner |',
            '| --- | --- | --- | --- |',
        ];

        foreach (range(0, 4) as $offset) {
            $date = $plan->week_start_date->copy()->addDays($offset);

            $cells = collect(MealSlot::cases())->map(function (MealSlot $slot) use ($plan, $date) {
                $meal = $this->mealFor($plan, $date, $slot);

                if ($meal === null) {
                    return '—';
                }

                $total = $meal->recipe->prep_minutes + $meal->recipe->cook_minutes;

                return "{$meal->recipe->title} ({$total} min)";
            });

            $lines[] = '| '.$date->format('D Y-m-d').' | '.$cells->implode(' | ').' |';
        }

        return implode("\n", $lines)."\n";
    }

    private function todayMarkdown(MealPlan $plan): string
    {
        $today = today();

        $lines = ['# Today — '.$today->format('D Y-m-d'), ''];

        foreach (MealSlot::cases() as $slot) {
            $meal = $this->mealFor($plan, $today, $slot);

            $lines[] = $meal === null
                ? '- '.ucfirst($slot->value).': —'
                : sprintf(
                    '- %s: %s (prep %d min, cook %d min)',
                    ucfirst($slot->value),
                    $meal->recipe->title,
                    $meal->recipe->prep_minutes,
                    $meal->recipe->cook_minutes,
                );
        }

        $lines[] = '';
        $lines[] = 'Prep ahead for tomorrow:';

        $prepLines = [];

        foreach (MealSlot::cases() as $slot) {
            $meal = $this->mealFor($plan, $today->copy()->addDay(), $slot);

            if ($meal !== null && $meal->recipe->prep_minutes > 0) {
                $prepLines[] = sprintf(
                    '- %s (%s): prep %d min',
                    $meal->recipe->title,
                    $slot->value,
                    $meal->recipe->prep_minutes,
                );
            }
        }

        return implode("\n", [...$lines, ...($prepLines ?: ['- Nothing to prep ahead.'])])."\n";
    }

    private function coversToday(MealPlan $plan): bool
    {
        // The plan grid covers Mon–Fri only.
        return today()->betweenIncluded(
            $plan->week_start_date,
            $plan->week_start_date->copy()->addDays(4)->endOfDay(),
        );
    }

    private function mealFor(MealPlan $plan, Carbon $date, MealSlot $slot): ?PlannedMeal
    {
        return $plan->plannedMeals->first(
            fn (PlannedMeal $meal) => $meal->date->isSameDay($date) && $meal->slot === $slot,
        );
    }
}
