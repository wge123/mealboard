<?php

namespace App\Discovery;

use App\Enums\DiscoveryLane;
use App\Exceptions\RecipePageNotFetched;
use App\Models\RecipeSite;
use App\Models\SeenPost;
use App\Support\HouseholdPreferences;
use Illuminate\Support\Facades\Log;

/**
 * Recipe-site driver (ADR 0004): reads each active recipe site's RSS feed,
 * fetches each post it has not seen through the page reader, and returns up
 * to $n candidates that pass the scheduled lane's household checks.
 *
 * Every post fetched is remembered (seen_posts), skipped and refused ones
 * included, so a page is never fetched twice. The weekday time, no-stated-time
 * and avoided-ingredient checks run on the page's raw recipe data before
 * anything is restructured. A site that will not answer is a failure kept in
 * failures(), not a thrown error: the other sites still run, and the run
 * records the failure.
 */
class RecipeSiteDriver implements RecipeDiscoveryDriver, ReportsPartialFailures
{
    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private RecipePageFetcher $fetcher,
        private RecipePageReader $reader,
        private CandidateValidator $validator,
        private HouseholdPreferences $preferences,
    ) {}

    public function discover(int $n): array
    {
        $this->failures = [];
        $candidates = [];

        foreach (RecipeSite::query()->where('active', true)->orderBy('id')->get() as $site) {
            if (count($candidates) >= $n) {
                break;
            }

            try {
                $links = $this->unseenPostLinks($this->fetcher->fetch($site->feed_url), $site->feed_url);
            } catch (RecipePageNotFetched|FeedNotReadable $e) {
                $this->fail($site, $e);

                continue;
            }

            foreach ($links as $link) {
                if (count($candidates) >= $n) {
                    break;
                }

                try {
                    $html = $this->fetcher->fetch($link);
                } catch (RecipePageNotFetched $e) {
                    // Not remembered: a blocked page is tried again tomorrow, and the
                    // rest of this site's posts wait, since the site is saying no.
                    $this->fail($site, $e);

                    break;
                }

                SeenPost::query()->firstOrCreate(['url' => $link]);

                if (($candidate = $this->candidateFrom($html, $link)) !== null) {
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    public function failures(): array
    {
        return $this->failures;
    }

    /** A site the run could not read: kept for the run's record, with its cause also in the log. */
    private function fail(RecipeSite $site, RecipePageNotFetched|FeedNotReadable $e): void
    {
        $this->failures[] = "{$site->name}: {$e->getMessage()}";

        Log::warning("recipes:discover could not read {$site->name}: {$e->getMessage()}");
    }

    /**
     * @return ?array<string, mixed> the restructured candidate, or null when the post is skipped or refused
     */
    private function candidateFrom(string $html, string $link): ?array
    {
        $preview = $this->reader->preview($html);

        if ($preview === null) {
            return null; // an article, not a recipe: skipped quietly
        }

        $early = $this->preferences->refusalsBeforeRestructuring($preview['ingredients'], $preview['prep_minutes'], $preview['cook_minutes'], $preview['servings']);

        if ($early !== []) {
            return $this->refused($link, $early);
        }

        $reading = $this->reader->read($html, $link);

        if ($reading->candidate === null) {
            return $this->refused($link, [$reading->refusal->message()]);
        }

        $check = $this->validator->check($reading->candidate);

        if (! $check->passes()) {
            return $this->refused($link, $check->errors);
        }

        // The ingredient limit needs cleaned names; it is the one check left for here.
        $refusals = $this->preferences->refusals($check->candidate, DiscoveryLane::Scheduled);

        return $refusals === [] ? $check->candidate : $this->refused($link, $refusals);
    }

    /**
     * @param  list<string>  $reasons
     */
    private function refused(string $link, array $reasons): null
    {
        Log::info("recipes:discover refused post {$link}: ".implode('; ', $reasons));

        return null;
    }

    /**
     * The post addresses of an RSS feed that no earlier run fetched, in feed
     * order, without tracking query strings.
     *
     * @return list<string>
     *
     * @throws FeedNotReadable
     */
    private function unseenPostLinks(string $xml, string $feedUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $feed = simplexml_load_string($xml);
        $reason = trim(implode('; ', array_map(fn ($error) => trim($error->message), libxml_get_errors())));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($feed === false) {
            throw new FeedNotReadable("{$feedUrl} is not XML the app can read: {$reason}");
        }

        if (! isset($feed->channel)) {
            throw new FeedNotReadable("{$feedUrl} is not an RSS feed the app can read: it has no channel");
        }

        $links = [];

        foreach ($feed->channel->item as $item) {
            $link = trim((string) $item->link);

            if ($link !== '') {
                $links[] = explode('#', explode('?', $link)[0])[0];
            }
        }

        $links = array_values(array_unique($links));
        $seen = SeenPost::query()->whereIn('url', $links)->pluck('url')->all();

        return array_values(array_diff($links, $seen));
    }
}
