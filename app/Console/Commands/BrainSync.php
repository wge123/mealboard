<?php

namespace App\Console\Commands;

use App\Models\BrainNote;
use App\Support\GitHubClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BrainSync extends Command
{
    protected $signature = 'brain:sync';

    protected $description = 'Pull preference notes from the second-brain vault repo into brain_notes';

    public function handle(GitHubClient $github): int
    {
        // Deviation from the original step text: DECISIONS.md #5 homes the
        // YouTube channel list in the youtube_channels table + the
        // /settings/channels UI, so channel sync is NOT part of brain:sync —
        // only food/recipe preference notes are pulled here.
        $repo = config('mealboard.brain_repo');
        $paths = config('mealboard.brain_files');

        $synced = 0;

        foreach ($paths as $path) {
            $content = $github->getRawFile($repo, $path);

            if ($content === null) {
                // Per-file misses stay non-fatal (a renamed note should not take
                // the run down), but they must survive the cron redirect, so
                // they go to the log and not only to stdout.
                $this->warn("{$path}: not found in {$repo} — skipped.");
                Log::warning("brain:sync could not fetch {$path} from {$repo} (404); the note was skipped.");

                continue;
            }

            BrainNote::query()->updateOrCreate(
                ['path' => $path],
                ['content' => $content, 'fetched_at' => now()],
            );

            $synced++;
            $this->info("{$path}: synced.");
        }

        // Syncing NOTHING is not a skip, it is a total loss of the preference
        // data discovery is built on. Downstream it degrades silently: the
        // discovery prompt just renders "(none yet)" and keeps producing
        // recipes that ignore the user's stated preferences, for as long as
        // nobody notices. The exit code is what makes that noticeable.
        if ($paths !== [] && $synced === 0) {
            $this->error("No brain notes synced from {$repo}; every configured path was missing.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
