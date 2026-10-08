<?php

namespace App\Console\Commands;

use App\Actions\Planning\BuildShoppingList;
use App\Models\MealPlan;
use App\Walmart\ListBrowser;
use Illuminate\Console\Command;
use RuntimeException;

class WalmartPushList extends Command
{
    protected $signature = 'walmart:push-list
        {--week= : Week start date (YYYY-MM-DD, a Monday); defaults to the latest locked week}';

    protected $description = "Push the week's buy list onto the configured Walmart list via a headed Chrome (list adds only — never cart or checkout)";

    public function handle(BuildShoppingList $buildShoppingList): int
    {
        // Fail fast BEFORE any browser or DB work: both settings are required.
        $profile = config('mealboard.chrome_profile');

        if (! is_string($profile) || trim($profile) === '') {
            throw new RuntimeException(
                'MEALBOARD_CHROME_PROFILE is not set — walmart:push-list drives a headed Chrome '
                .'with a persistent logged-in profile. Set it to the Chrome user-data directory '
                .'in .env (see docs/tier3-list.md).',
            );
        }

        $listUrl = config('mealboard.walmart_list_url');

        if (! is_string($listUrl) || trim($listUrl) === '') {
            throw new RuntimeException(
                'MEALBOARD_WALMART_LIST_URL is not set — set it to your Walmart list page URL '
                .'in .env (see docs/tier3-list.md).',
            );
        }

        $items = array_column($buildShoppingList->handle($this->plan($this->option('week')))['buy_list'], 'keywords');

        if ($items === []) {
            $this->components->info('Nothing to push — the buy list is empty.');

            return self::SUCCESS;
        }

        $total = count($items);
        $browser = app(ListBrowser::class);

        try {
            $browser->open($listUrl);

            foreach ($items as $index => $keywords) {
                $n = $index + 1;

                try {
                    $browser->addItem($keywords);
                } catch (RuntimeException $e) {
                    // Abort the WHOLE run on the first miss — a silent partial
                    // push is worse than a loud stop.
                    throw new RuntimeException(sprintf(
                        '%s — failed on item %d of %d ("%s"); list may be half-filled up to item %d; inspect docs/tier3-list.md and update the selectors.',
                        $e->getMessage(),
                        $n,
                        $total,
                        $keywords,
                        $n - 1,
                    ), previous: $e);
                }

                $this->components->twoColumnDetail($keywords, sprintf('added %d/%d', $n, $total));
            }
        } finally {
            $browser->close();
        }

        $this->components->info(sprintf(
            'Pushed %d item%s to the Walmart list. Review the list in the browser — nothing was carted or purchased.',
            $total,
            $total === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * The named week, else the latest locked one — refusing a stale latest
     * week (GLOSSARY: Stale week), which is almost never the one meant.
     * Naming the week explicitly is the override.
     */
    private function plan(?string $weekStart): MealPlan
    {
        if ($weekStart !== null) {
            return MealPlan::query()->whereDate('week_start_date', $weekStart)->first()
                ?? throw new RuntimeException("No meal plan for week starting {$weekStart}.");
        }

        $plan = MealPlan::latestLocked() ?? throw new RuntimeException('No locked week.');
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
