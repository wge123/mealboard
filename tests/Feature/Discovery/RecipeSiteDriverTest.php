<?php

use App\Actions\Recipes\RestructureIngredientLine;
use App\Discovery\RecipeSiteDriver;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\DiscoveryRun;
use App\Models\Recipe;
use App\Models\RecipeSite;
use App\Models\SeenPost;
use App\Support\HouseholdPreferences;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const SITE_FEED = 'https://cook.example/feed/';

function siteFeed(array $links): string
{
    $items = '';

    foreach ($links as $link) {
        $items .= "<item><title>Post</title><link>{$link}</link></item>";
    }

    return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Cook</title>'.$items.'</channel></rss>';
}

function sitePage(string $title, array $ingredients = ['1 cup rice', '2 eggs'], ?string $prep = 'PT10M', ?string $cook = 'PT15M'): string
{
    $recipe = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => $title,
        'description' => "A fine {$title}.",
        'prepTime' => $prep,
        'cookTime' => $cook,
        'recipeYield' => '4',
        'recipeIngredient' => $ingredients,
        'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Cook everything in a large skillet.'], ['@type' => 'HowToStep', 'text' => 'Serve.']],
    ], fn ($v) => $v !== null);

    return '<html><head><script type="application/ld+json">'.json_encode($recipe).'</script></head><body></body></html>';
}

function fakeSite(array $pages, string $feedUrl = SITE_FEED): void
{
    Http::fake([$feedUrl => Http::response(siteFeed(array_keys($pages)))]
        + array_map(fn ($html) => Http::response($html), $pages));
}

function timesFetched(string $url): int
{
    return count(Http::recorded(fn (Request $r) => $r->url() === $url));
}

beforeEach(function () {
    config()->set('mealboard.drivers', [RecipeSiteDriver::class]);
    Http::preventStrayRequests();
    RecipeSite::query()->delete(); // the migration lists the two real sites; these tests use their own
    RecipeSite::factory()->create(['feed_url' => SITE_FEED, 'name' => 'Cook']);
});

it('stores new recipe posts as pending discovered recipes with the post address as source', function () {
    fakeSite([
        'https://cook.example/a/' => sitePage('Alpha Rice'),
        'https://cook.example/b/' => sitePage('Bravo Beans'),
        'https://cook.example/c/' => sitePage('Charlie Chili'),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();

    $recipe = Recipe::firstWhere('title', 'Alpha Rice');

    expect(Recipe::count())->toBe(3)
        ->and($recipe->status)->toBe(RecipeStatus::Pending)
        ->and($recipe->source)->toBe(RecipeSource::Discovered)
        ->and($recipe->source_url)->toBe('https://cook.example/a/')
        ->and(SeenPost::count())->toBe(3)
        ->and(DiscoveryRun::sole()->candidates_found)->toBe(3);
});

it('sends the apps own honest User-Agent', function () {
    fakeSite(['https://cook.example/a/' => sitePage('Alpha Rice')]);

    $this->artisan('recipes:discover')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0] ?? '', 'Mealboard/'));
});

it('does not fetch a post it fetched on an earlier run', function () {
    SeenPost::create(['url' => 'https://cook.example/a/']);

    fakeSite([
        'https://cook.example/a/' => sitePage('Alpha Rice'),
        'https://cook.example/b/' => sitePage('Bravo Beans'),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();

    expect(timesFetched('https://cook.example/a/'))->toBe(0)
        ->and(Recipe::pluck('title')->all())->toBe(['Bravo Beans']);
});

it('never fetches a page twice across runs, whether it was stored, skipped or refused', function () {
    fakeSite([
        'https://cook.example/a/' => sitePage('Alpha Rice'),
        'https://cook.example/b/' => '<html><body>An article.</body></html>',
        'https://cook.example/c/' => sitePage('Slow Stew', ['1 cup rice'], 'PT1H', 'PT2H'),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();
    $this->artisan('recipes:discover', ['--force' => true])->assertSuccessful();

    expect(timesFetched('https://cook.example/a/'))->toBe(1)
        ->and(timesFetched('https://cook.example/b/'))->toBe(1)
        ->and(timesFetched('https://cook.example/c/'))->toBe(1)
        ->and(SeenPost::count())->toBe(3);
});

it('skips an article post with no recipe data and stores nothing for it', function () {
    fakeSite(['https://cook.example/news/' => '<html><body>Cookbook news.</body></html>']);

    $this->artisan('recipes:discover')->assertSuccessful();

    expect(Recipe::count())->toBe(0)
        ->and(SeenPost::count())->toBe(1);
});

it('refuses posts over the weekday limits, with an avoided ingredient or with no time, before restructuring', function () {
    app(HouseholdPreferences::class)->avoid('cilantro');

    $counter = new ArrayObject(['restructured' => 0]);
    app()->bind(RestructureIngredientLine::class, fn () => new class($counter) extends RestructureIngredientLine
    {
        public function __construct(private ArrayObject $counter) {}

        public function handle(string $line): array
        {
            $this->counter['restructured']++;

            return parent::handle($line);
        }
    });

    fakeSite([
        'https://cook.example/slow/' => sitePage('Slow Stew', ['1 cup rice'], 'PT1H', 'PT2H'),
        'https://cook.example/herb/' => sitePage('Herb Rice', ['1 cup rice', '1 bunch fresh cilantro']),
        'https://cook.example/untimed/' => sitePage('Untimed Rice', ['1 cup rice'], null, null),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();

    expect(Recipe::count())->toBe(0)
        ->and($counter['restructured'])->toBe(0)
        ->and(SeenPost::count())->toBe(3);
});

it('stores only three when more than three posts survive', function () {
    fakeSite(collect(['a' => 'Alpha Rice', 'b' => 'Bravo Beans', 'c' => 'Charlie Chili', 'd' => 'Delta Dal', 'e' => 'Echo Eggs'])
        ->mapWithKeys(fn ($title, $key) => ["https://cook.example/{$key}/" => sitePage($title)])->all());

    $this->artisan('recipes:discover')->assertSuccessful();

    expect(Recipe::count())->toBe(3);
});

it('records a site that answers 403 on the run and still reads the other sites', function () {
    RecipeSite::factory()->create(['feed_url' => 'https://other.example/feed/', 'name' => 'Other']);

    Http::fake([
        SITE_FEED => Http::response('Forbidden', 403),
        'https://other.example/feed/' => Http::response(siteFeed(['https://other.example/a/'])),
        'https://other.example/a/' => Http::response(sitePage('Other Rice')),
    ]);

    $this->artisan('recipes:discover')
        ->expectsOutputToContain('403')
        ->assertFailed();

    expect(Recipe::pluck('title')->all())->toBe(['Other Rice'])
        ->and(DiscoveryRun::sole()->error)->toContain(SITE_FEED)->toContain('403');
});

it('does not read a paused site', function () {
    RecipeSite::query()->update(['active' => false]);
    Http::fake();

    $this->artisan('recipes:discover')->assertSuccessful();

    Http::assertNothingSent();
});
