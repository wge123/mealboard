<?php

use App\Actions\Recipes\ImportRecipePage;
use App\Discovery\RecipeSiteDriver;
use App\Enums\RecipePageRefusal;
use App\Models\DiscoveryRun;
use App\Models\RecipeSite;
use App\Models\SeenPost;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const FETCH_FEED = 'https://fetch.example/feed/';
const FETCH_AGENT = 'Mealboard/1.0 (household recipe planner)';
const CHALLENGE_BODY = '<html><head><title>Just a moment...</title></head><body><script src="/cdn-cgi/challenge-platform/h/b/orchestrate/chl_page/v1"></script></body></html>';

function fetchFeed(string ...$links): string
{
    $items = implode('', array_map(fn ($link) => "<item><link>{$link}</link></item>", $links));

    return '<?xml version="1.0"?><rss version="2.0"><channel><title>Fetch</title>'.$items.'</channel></rss>';
}

beforeEach(function () {
    config()->set('mealboard.drivers', [RecipeSiteDriver::class]);
    Http::preventStrayRequests();
    RecipeSite::query()->delete();
    RecipeSite::factory()->create(['feed_url' => FETCH_FEED, 'name' => 'Fetch']);
});

it('sends one honest User-Agent on the feed and on every post, never a browser string', function () {
    Http::fake([
        FETCH_FEED => Http::response(fetchFeed('https://fetch.example/a/')),
        'https://fetch.example/a/' => Http::response('<html><body>An article.</body></html>'),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();

    $agents = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('User-Agent')[0] ?? '')->unique()->values()->all();

    expect($agents)->toBe([FETCH_AGENT]);
});

it('sends the same honest User-Agent when a recipe link is imported', function () {
    Http::fake(['*' => Http::response('<html><body>An article.</body></html>')]);

    app(ImportRecipePage::class)->handle('https://fetch.example/a/');

    $agents = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('User-Agent')[0] ?? '')->unique()->values()->all();

    expect($agents)->toBe([FETCH_AGENT]);
});

it('keeps the reason a feed could not be read in the run error', function () {
    Http::fake([FETCH_FEED => Http::response('<html><body>Not a feed')]);

    $this->artisan('recipes:discover')->assertFailed();

    expect(DiscoveryRun::sole()->error)->toContain(FETCH_FEED)->toContain('Premature end of data');
});

it('treats a challenge page served with a 200 and no header as a post it could not fetch, and tries it again later', function () {
    Http::fake([
        FETCH_FEED => Http::response(fetchFeed('https://fetch.example/a/')),
        'https://fetch.example/a/' => Http::response(CHALLENGE_BODY),
    ]);

    $this->artisan('recipes:discover')->assertFailed();

    expect(DiscoveryRun::sole()->error)->toContain('challenge')
        ->and(SeenPost::count())->toBe(0);
});

it('does not take an ordinary page that says "Just a moment" in its title for a challenge', function () {
    Http::fake(['*' => Http::response('<html><head><title>Just a moment...</title></head><body>A blog post.</body></html>')]);

    $reading = app(ImportRecipePage::class)->handle('https://fetch.example/a/');

    expect($reading->refusal)->toBe(RecipePageRefusal::NoRecipeData);
});

it('refuses a challenge page on an import as could not fetch', function () {
    Http::fake(['*' => Http::response(CHALLENGE_BODY)]);

    $reading = app(ImportRecipePage::class)->handle('https://fetch.example/a/');

    expect($reading->refusal)->toBe(RecipePageRefusal::CouldNotFetch);
});

it('logs the cause where a driver fetch failure becomes a recorded failure', function () {
    Log::spy();
    Http::fake([FETCH_FEED => Http::response('Forbidden', 403)]);

    $this->artisan('recipes:discover')->assertFailed();

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, FETCH_FEED) && str_contains($message, '403'))->once();
});

it('logs the cause where an import fetch failure becomes a refusal', function (Closure $fake, string $cause) {
    Log::spy();
    Http::fake(['*' => $fake]);

    $reading = app(ImportRecipePage::class)->handle('https://fetch.example/a/');

    expect($reading->refusal)->toBe(RecipePageRefusal::CouldNotFetch);
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'https://fetch.example/a/') && str_contains($message, $cause))->once();
})->with([
    'a 403' => [fn () => Http::response('Denied', 403), '403'],
    'a timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out'), 'timed out'],
    'a challenge' => [fn () => Http::response('x', 403, ['cf-mitigated' => 'challenge']), 'challenge'],
]);
