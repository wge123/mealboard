<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vault preference notes sync BEFORE discovery so the day's discovery
// prompt sees fresh notes (step 25).
Schedule::command('brain:sync')->dailyAt('05:15');

// DECISIONS.md #1 — discovery runs DAILY (3 candidates per driver, config).
Schedule::command('recipes:discover')->dailyAt('05:30');

// Nightly today.md refresh so the vault shows the new day's meals from the
// current locked week (step 26). Skips quietly when no locked week covers today.
Schedule::command('meals:publish-today')->dailyAt('00:10');
