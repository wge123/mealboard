<?php

namespace App\Console\Commands;

use App\Actions\Discovery\RunRequest;
use App\Enums\RequestStatus;
use App\Models\RecipeRequest;
use Illuminate\Console\Command;

class RequestRecipes extends Command
{
    protected $signature = 'recipes:request {query : What the household wants, e.g. "hibachi for 4 on a flat-top griddle"}';

    protected $description = 'Search YouTube and ask claude for one specific dish, storing the results as pending recipes';

    public function handle(RunRequest $runRequest): int
    {
        $query = trim((string) $this->argument('query'));

        if ($query === '') {
            $this->error('The request cannot be empty.');

            return self::FAILURE;
        }

        $request = RecipeRequest::create(['query' => $query]);

        $this->info("Requesting: {$query}");

        $created = $runRequest->handle($request);

        $request->refresh();

        if ($request->error !== null) {
            $this->warn($request->error);
        }

        // A request whose every lane died reports failure through the exit
        // code, which is the only channel an unattended caller can see. Zero
        // candidates from lanes that RAN is not a failure: the household asked
        // for something nothing could match, and that is an answer.
        if ($request->status === RequestStatus::Failed) {
            $this->error('Every lane failed; no candidates were stored.');

            return self::FAILURE;
        }

        $this->info("Created {$created} pending recipe(s) for review at /approve.");

        return self::SUCCESS;
    }
}
