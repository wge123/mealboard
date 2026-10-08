<?php

use App\Models\MealPlan;
use App\Walmart\ListBrowser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/*
 * Only the fail-fast config guards are tested — the browser path is
 * deliberately unverified here (docs/tier3-list.md: the live supervised run
 * is its verification, and tests must never launch Chrome).
 */

it('refuses to start when MEALBOARD_CHROME_PROFILE is unset', function () {
    config(['mealboard.chrome_profile' => null, 'mealboard.walmart_list_url' => 'https://www.walmart.com/lists/example']);

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'MEALBOARD_CHROME_PROFILE is not set');
});

it('refuses to start when MEALBOARD_WALMART_LIST_URL is unset', function () {
    config(['mealboard.chrome_profile' => '/tmp/mealboard-chrome', 'mealboard.walmart_list_url' => null]);

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'MEALBOARD_WALMART_LIST_URL is not set');
});

it('exits non-zero naming the stale week before any browser work', function () {
    config(['mealboard.chrome_profile' => '/tmp/mealboard-chrome', 'mealboard.walmart_list_url' => 'https://www.walmart.com/lists/example']);
    $this->mock(ListBrowser::class)->shouldNotReceive('open');

    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'The latest locked week (2026-07-20) is 5 weeks stale');
});
