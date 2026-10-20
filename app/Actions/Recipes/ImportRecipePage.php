<?php

namespace App\Actions\Recipes;

use App\Discovery\RecipePageFetcher;
use App\Discovery\RecipePageReader;
use App\Discovery\RecipePageReading;
use App\Enums\RecipePageRefusal;
use App\Exceptions\RecipePageNotFetched;
use Illuminate\Support\Facades\Log;

/**
 * Fetches one recipe page and reads it. A page the site will not give is a
 * named refusal, never a guess.
 */
class ImportRecipePage
{
    public function __construct(private RecipePageFetcher $fetcher, private RecipePageReader $reader) {}

    public function handle(string $url): RecipePageReading
    {
        try {
            $body = $this->fetcher->fetch($url);
        } catch (RecipePageNotFetched $e) {
            // The site is the boundary: a page it will not give is a refusal the household sees,
            // and the cause (status, challenge, timeout) is kept in the log.
            Log::warning("recipe import refused: {$e->getMessage()}");

            return new RecipePageReading(null, RecipePageRefusal::CouldNotFetch);
        }

        return $this->reader->read($body, $url);
    }
}
