<?php

use App\Discovery\TheMealDbDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Recipe discovery
    |--------------------------------------------------------------------------
    |
    | DECISIONS.md #1 — discovery runs DAILY, 3 candidates per run.
    | One run executes ALL listed drivers; each driver is asked for
    | candidates_per_run candidates and failures are recorded per driver.
    |
    */

    'cadence' => 'daily',

    'candidates_per_run' => 3,

    'drivers' => [
        TheMealDbDriver::class,
    ],

];
