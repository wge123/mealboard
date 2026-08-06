<?php

namespace App\Actions\Discovery;

use App\Models\DiscoveryRun;
use Throwable;

class RunDiscovery
{
    /**
     * Execute every configured discovery driver, aggregating candidates.
     *
     * A throwing driver is recorded (DiscoveryRun row + errors entry) and the
     * remaining drivers still run.
     *
     * @return array{candidates: array<int, array<string, mixed>>, errors: array<class-string, string>}
     */
    public function handle(?int $count = null): array
    {
        $count ??= (int) config('mealboard.candidates_per_run');

        $candidates = [];
        $errors = [];

        foreach (config('mealboard.drivers', []) as $driverClass) {
            $error = null;
            $found = [];

            try {
                $found = app($driverClass)->discover($count);
                $candidates = [...$candidates, ...$found];
            } catch (Throwable $e) {
                // report() before reducing the exception to its message: the
                // DiscoveryRun row keeps one line, and without this the stack
                // trace of an unattended failure is destroyed here.
                report($e);
                $error = $e->getMessage();
                $errors[$driverClass] = $error;
            }

            DiscoveryRun::create([
                'ran_at' => now(),
                'driver' => $driverClass,
                'candidates_found' => count($found),
                'error' => $error,
            ]);
        }

        return ['candidates' => $candidates, 'errors' => $errors];
    }
}
