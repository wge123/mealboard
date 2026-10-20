<?php

namespace App\Actions\Recipes;

use App\Discovery\RecipePageReader;
use App\Discovery\RecipePageReading;
use App\Enums\RecipePageRefusal;
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
            $response = Http::withUserAgent('Mealboard/1.0 (household recipe import)')
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException) {
            // The site is the boundary: an unreachable one is a refusal the household sees.
            return new RecipePageReading(null, RecipePageRefusal::CouldNotFetch);
        }

        // A bot-wall challenge is the site refusing the app, whatever status it wears.
        if (! $response->successful() || $response->header('cf-mitigated') === 'challenge') {
            return new RecipePageReading(null, RecipePageRefusal::CouldNotFetch);
        }

        return $this->reader->read($response->body(), $url);
    }
}
