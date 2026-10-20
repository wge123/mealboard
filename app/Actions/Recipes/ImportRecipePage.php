<?php

namespace App\Actions\Recipes;

use App\Discovery\RecipePageReader;
use App\Discovery\RecipePageReading;
use App\Enums\RecipePageRefusal;
use App\Exceptions\RecipePageNotFetched;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches one recipe page and reads it. The app names itself honestly in
 * the User-Agent; a page it cannot get is a named refusal, never a guess.
 */
class ImportRecipePage
{
    public function __construct(private RecipePageReader $reader) {}

    public function handle(string $url): RecipePageReading
    {
        try {
            $body = $this->fetchBody($url);
        } catch (RecipePageNotFetched) {
            // The site is the boundary: a page it will not give is a refusal the household sees.
            return new RecipePageReading(null, RecipePageRefusal::CouldNotFetch);
        }

        return $this->reader->read($body, $url);
    }

    /**
     * The body of one page or feed, fetched as the app names itself.
     *
     * @throws RecipePageNotFetched on a non-200, a bot-wall challenge or a timeout
     */
    public function fetchBody(string $url): string
    {
        try {
            $response = Http::withUserAgent('Mealboard/1.0 (household recipe import)')
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RecipePageNotFetched("Could not fetch {$url}: {$e->getMessage()}", previous: $e);
        }

        // A bot-wall challenge is the site refusing the app, whatever status it wears.
        if ($response->header('cf-mitigated') === 'challenge') {
            throw new RecipePageNotFetched("Could not fetch {$url}: the site answered with a bot-wall challenge");
        }

        if (! $response->successful()) {
            throw new RecipePageNotFetched("Could not fetch {$url}: the site answered {$response->status()}");
        }

        return $response->body();
    }
}
