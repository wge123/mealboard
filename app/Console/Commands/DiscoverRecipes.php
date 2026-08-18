<?php

namespace App\Console\Commands;

use App\Actions\Discovery\RunDiscovery;
use App\Actions\Recipes\CreateRecipe;
use App\Enums\RecipeSource;
use App\Models\DiscoveryRun;
use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiscoverRecipes extends Command
{
    protected $signature = 'recipes:discover {--force : Run even if discovery already ran today}';

    protected $description = 'Run all discovery drivers and store new candidates as pending recipes';

    public function handle(RunDiscovery $runDiscovery, CreateRecipe $createRecipe): int
    {
        // Idempotent per day — the scheduler fires daily (DECISIONS.md #1)
        // and a manual rerun should not double the day's candidates.
        if (! $this->option('force') && DiscoveryRun::query()->whereDate('ran_at', today())->exists()) {
            $this->info('Discovery already ran today — skipping (use --force to rerun).');

            return self::SUCCESS;
        }

        $result = $runDiscovery->handle();

        foreach ($result['errors'] as $driver => $error) {
            $this->warn("{$driver}: {$error}");
            Log::error("recipes:discover driver failed: {$driver}: {$error}");
        }

        // Fuzzy title dedupe against ALL existing recipes — rejected included,
        // so a rejected suggestion is never re-suggested — and within the batch.
        $knownTitles = Recipe::query()
            ->pluck('title')
            ->map(fn (string $title) => mb_strtolower(trim($title)))
            ->all();

        $created = 0;
        $skipped = 0;

        foreach ($result['candidates'] as $candidate) {
            if ($this->isNearDuplicate($candidate['title'], $knownTitles)) {
                $skipped++;

                continue;
            }

            $ingredients = $candidate['ingredients'];
            unset($candidate['ingredients']);

            $createRecipe->handle([
                ...$candidate,
                'source' => RecipeSource::Discovered,
                'discovered_at' => now(),
            ], $ingredients);

            $knownTitles[] = mb_strtolower(trim($candidate['title']));
            $created++;
        }

        $this->info("Created {$created} pending recipe(s), skipped {$skipped} duplicate(s).");

        // A driver that threw is a real failure even when the surviving drivers
        // produced candidates, and the exit code is the only thing the
        // scheduler's onFailure hook can see. Returning SUCCESS here is how a
        // run in which every driver died still looked like a perfect day.
        //
        // Candidates created above are already committed and the per-day
        // idempotency guard still holds, so a retry of this command re-runs the
        // drivers without duplicating what this run stored.
        if ($result['errors'] !== []) {
            $this->error(count($result['errors']).' discovery driver(s) failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $knownTitles  lowercased, trimmed
     */
    private function isNearDuplicate(string $title, array $knownTitles): bool
    {
        $needle = mb_strtolower(trim($title));

        foreach ($knownTitles as $known) {
            if (levenshtein($needle, $known) <= 3) {
                return true;
            }

            similar_text($needle, $known, $percent);

            if ($percent >= 85) {
                return true;
            }
        }

        return false;
    }
}
