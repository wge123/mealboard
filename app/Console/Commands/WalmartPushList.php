<?php

namespace App\Console\Commands;

use App\Actions\Planning\BuildShoppingList;
use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use App\Walmart\ListBrowser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
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

        $buyList = $buildShoppingList->handle($this->plan($this->option('week')))['buy_list'];

        // The list page adds an item by searching its keywords, and an empty
        // search adds nothing useful: say which entries were left off.
        foreach ($buyList as $entry) {
            if ($entry['keywords'] === '') {
                $this->components->warn("Skipped \"{$entry['name']}\": its search keywords are empty, so the list cannot search for it.");
            }
        }

        $buyListKeywords = array_values(array_filter(array_column($buyList, 'keywords'), fn (string $keywords) => $keywords !== ''));

        if ($buyListKeywords === []) {
            $this->components->info('Nothing to push — the buy list is empty.');

            return self::SUCCESS;
        }

        $total = count($buyListKeywords);
        $browser = app(ListBrowser::class);

        try {
            $browser->open($listUrl);

            foreach ($buyListKeywords as $index => $keywords) {
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
            'Pushed %d %s to the Walmart list. Review the list in the browser — nothing was carted or purchased.',
            $total,
            Str::plural('item', $total),
        ));

        return self::SUCCESS;
    }

    /**
     * The named week, else the latest locked one — refusing a stale latest
     * week (GLOSSARY: Stale week), which is almost never the one meant.
     * Naming the week explicitly is the override; a named draft week has no
     * shopping list yet, so it is refused here rather than deep in the module.
     */
    private function plan(?string $weekStart): MealPlan
    {
        if ($weekStart !== null) {
            $plan = MealPlan::forWeek($weekStart)
                ?? throw new RuntimeException("No meal plan for week starting {$weekStart}.");

            if ($plan->status === MealPlanStatus::Draft) {
                throw new RuntimeException("The week starting {$weekStart} is a draft — lock it before pushing its list.");
            }

            return $plan;
        }

        $plan = MealPlan::latestLocked() ?? throw new RuntimeException('No locked week.');
        $weeksStale = $plan->weeksStale();

        if ($weeksStale > 0) {
            $week = $plan->week_start_date->toDateString();

            throw new RuntimeException(sprintf(
                'The latest locked week (%s) is %d %s stale — lock a newer week, or pass --week=%s to push it anyway.',
                $week,
                $weeksStale,
                Str::plural('week', $weeksStale),
                $week,
            ));
        }

        return $plan;
    }
}
