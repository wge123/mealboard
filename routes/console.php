<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Route a scheduled-command failure to the machine-wide job failure reporter.
 * Reaching that script is what earns a job the HUD badge, the session-start
 * surfacing, and the autonomous repair pass; a scheduled entry point that does
 * not call it fails invisibly no matter how loudly it throws, because the
 * documented cron line sends stdout and stderr to /dev/null.
 *
 * The reporter never exits non-zero itself, so this hook cannot amplify or mask
 * the underlying failure.
 */
$notifyFail = function (string $job): void {
    $script = (string) config('mealboard.job_notify_fail');

    if ($script === '' || ! is_file($script)) {
        // Throwing inside a scheduler failure hook would replace one invisible
        // failure with another, so make the missing reporter loud in the log
        // instead of swallowing the fact that nobody was told.
        Log::error("Scheduled job {$job} failed and the job-notify-fail reporter is missing at '{$script}', so no notification was sent.");

        return;
    }

    Process::run(['sh', $script, $job, 'scheduled command exited non-zero']);
};

/*
 * appendOutputTo is not decoration: every one of these commands reports trouble
 * with $this->warn()/info() on stdout, which the cron line discards. Without it
 * the only surviving evidence of a bad run is the notification itself.
 */
$logFor = fn (string $name) => storage_path("logs/schedule-{$name}.log");

// Vault preference notes sync BEFORE discovery so the day's discovery
// prompt sees fresh notes (step 25).
Schedule::command('brain:sync')
    ->dailyAt('05:15')
    ->withoutOverlapping()
    ->appendOutputTo($logFor('brain-sync'))
    ->onFailure(fn () => $notifyFail('mealboard brain:sync'));

// DECISIONS.md #1 — discovery runs DAILY (3 candidates per driver, config).
// withoutOverlapping matters here specifically: a discovery run can legitimately
// block for many minutes (claude CLI timeout is 600s, transcripts 120s each),
// so a slow run must not be re-entered by the next tick.
Schedule::command('recipes:discover')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->appendOutputTo($logFor('recipes-discover'))
    ->onFailure(fn () => $notifyFail('mealboard recipes:discover'));

// Nightly today.md refresh so the vault shows the new day's meals from the
// current locked week (step 26). Skips quietly when no locked week covers today.
Schedule::command('meals:publish-today')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->appendOutputTo($logFor('meals-publish-today'))
    ->onFailure(fn () => $notifyFail('mealboard meals:publish-today'));
