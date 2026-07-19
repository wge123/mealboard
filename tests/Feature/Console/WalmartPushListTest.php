<?php

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
