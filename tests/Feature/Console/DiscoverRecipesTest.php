<?php

use App\Discovery\RecipeDiscoveryDriver;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\DiscoveryRun;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Console\Scheduling\Schedule;

class FakeDiscoveryDriver implements RecipeDiscoveryDriver
{
    /** @var array<int, array<string, mixed>> */
    public static array $candidates = [];

    public function discover(int $n): array
    {
        return static::$candidates;
    }
}

class FailingDiscoveryDriver implements RecipeDiscoveryDriver
{
    public function discover(int $n): array
    {
        throw new RuntimeException('driver blew up');
    }
}

function discoveredCandidate(string $title, array $overrides = []): array
{
    return array_merge([
        'title' => $title,
        'description' => 'A discovered recipe.',
        'meal_type' => 'dinner',
        'prep_minutes' => 10,
        'cook_minutes' => 15,
        'servings' => 2,
        'instructions' => "1. Cook.\n2. Serve.",
        'cuisine' => null,
        'tags' => ['quick'],
        'source_url' => 'https://www.youtube.com/watch?v=vid00000001',
        'ingredients' => [
            ['qty' => 2.0, 'unit' => 'cup', 'name' => 'spinach', 'note' => null],
            ['qty' => 1.0, 'unit' => null, 'name' => 'lemon', 'note' => 'juiced'],
        ],
    ], $overrides);
}

beforeEach(function () {
    config()->set('mealboard.drivers', [FakeDiscoveryDriver::class]);
    FakeDiscoveryDriver::$candidates = [];
});

it('stores each valid candidate as a pending discovered recipe with synced ingredients', function () {
    Ingredient::factory()->create(['name' => 'spinach']); // find-or-create must reuse this.

    FakeDiscoveryDriver::$candidates = [
        discoveredCandidate('Lemony Spinach Orzo'),
        discoveredCandidate('Crispy Chickpea Bowls'),
    ];

    $this->artisan('recipes:discover')
        ->expectsOutputToContain('Created 2 pending recipe(s)')
        ->assertSuccessful();

    $recipe = Recipe::firstWhere('title', 'Lemony Spinach Orzo');

    expect(Recipe::count())->toBe(2)
        ->and($recipe->status)->toBe(RecipeStatus::Pending)
        ->and($recipe->source)->toBe(RecipeSource::Discovered)
        ->and($recipe->discovered_at)->not->toBeNull()
        ->and($recipe->source_url)->toBe('https://www.youtube.com/watch?v=vid00000001')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and(Ingredient::where('name', 'spinach')->count())->toBe(1)
        ->and(DiscoveryRun::count())->toBe(1);
});

it('skips near-duplicate titles, including rejected recipes and within the batch', function () {
    Recipe::factory()->create(['title' => 'Garlic Butter Noodles', 'status' => RecipeStatus::Rejected]);

    FakeDiscoveryDriver::$candidates = [
        discoveredCandidate('Garlic Butter Noodle'), // levenshtein 1 vs rejected recipe
        discoveredCandidate('Miso Salmon Bowls'),
        discoveredCandidate('Miso Salmon Bowl'), // batch-internal near-dupe
    ];

    $this->artisan('recipes:discover')
        ->expectsOutputToContain('Created 1 pending recipe(s), skipped 2 duplicate(s).')
        ->assertSuccessful();

    expect(Recipe::count())->toBe(2)
        ->and(Recipe::firstWhere('title', 'Miso Salmon Bowls'))->not->toBeNull()
        ->and(Recipe::firstWhere('title', 'Garlic Butter Noodle'))->toBeNull();
});

it('no-ops when discovery already ran today unless forced', function () {
    FakeDiscoveryDriver::$candidates = [discoveredCandidate('First Run Frittata')];

    $this->artisan('recipes:discover')->assertSuccessful();

    FakeDiscoveryDriver::$candidates = [discoveredCandidate('Second Run Shakshuka')];

    $this->artisan('recipes:discover')
        ->expectsOutputToContain('already ran today')
        ->assertSuccessful();

    expect(Recipe::count())->toBe(1)
        ->and(DiscoveryRun::count())->toBe(1);

    $this->artisan('recipes:discover', ['--force' => true])->assertSuccessful();

    expect(Recipe::count())->toBe(2)
        ->and(Recipe::firstWhere('title', 'Second Run Shakshuka'))->not->toBeNull()
        ->and(DiscoveryRun::count())->toBe(2);
});

it('records a driver failure on the discovery_runs row and still succeeds', function () {
    config()->set('mealboard.drivers', [FailingDiscoveryDriver::class, FakeDiscoveryDriver::class]);
    FakeDiscoveryDriver::$candidates = [discoveredCandidate('Resilient Ratatouille')];

    $this->artisan('recipes:discover')
        ->expectsOutputToContain('driver blew up')
        ->assertSuccessful();

    $failedRun = DiscoveryRun::firstWhere('driver', FailingDiscoveryDriver::class);

    expect($failedRun->error)->toContain('driver blew up')
        ->and($failedRun->candidates_found)->toBe(0)
        ->and(Recipe::count())->toBe(1);
});

it('is scheduled daily at 05:30', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'recipes:discover'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 5 * * *');
});
