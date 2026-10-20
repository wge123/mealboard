<?php

namespace App\Discovery;

use App\Exceptions\RecipePageNotFetched;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches one recipe page or feed for the app: the import of a pasted link and
 * the recipe-site driver (feeds and posts) both come here. The app names itself
 * honestly, whatever it fetches; a page the site will not give is a
 * RecipePageNotFetched that keeps its cause (status, challenge or timeout).
 */
class RecipePageFetcher
{
    public const USER_AGENT = 'Mealboard/1.0 (household recipe planner)';

    /**
     * @throws RecipePageNotFetched on a non-200, a bot-wall challenge or a timeout
     */
    public function fetch(string $url): string
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException $e) {
            throw RecipePageNotFetched::timedOut($url, $e);
        }

        // A bot-wall challenge is the site refusing the app, whatever status it wears.
        if ($response->header('cf-mitigated') === 'challenge' || $this->isChallengePage($response->body())) {
            throw RecipePageNotFetched::challenged($url);
        }

        if (! $response->successful()) {
            throw RecipePageNotFetched::answered($url, $response->status());
        }

        return $response->body();
    }

    /**
     * Cloudflare's interstitial: the "Just a moment..." title together with
     * its challenge-platform script. The title alone could be an ordinary page.
     */
    private function isChallengePage(string $body): bool
    {
        return preg_match('/<title>\s*Just a moment\.\.\.\s*<\/title>/i', $body) === 1
            && str_contains($body, 'challenge-platform');
    }
}
