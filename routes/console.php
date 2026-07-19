<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// DECISIONS.md #1 — discovery runs DAILY (3 candidates per driver, config).
Schedule::command('recipes:discover')->dailyAt('05:30');
