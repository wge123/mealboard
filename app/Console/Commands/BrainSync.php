<?php

namespace App\Console\Commands;

use App\Models\BrainNote;
use App\Support\GitHubClient;
use Illuminate\Console\Command;

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

        foreach (config('mealboard.brain_files') as $path) {
            $content = $github->getRawFile($repo, $path);

            if ($content === null) {
                $this->warn("{$path}: not found in {$repo} — skipped.");

                continue;
            }

            BrainNote::query()->updateOrCreate(
                ['path' => $path],
                ['content' => $content, 'fetched_at' => now()],
            );

            $this->info("{$path}: synced.");
        }

        return self::SUCCESS;
    }
}
