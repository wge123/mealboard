<?php

use App\Actions\Discovery\RunDiscovery;
use App\Discovery\RecipeDiscoveryDriver;
use App\Models\DiscoveryRun;

class FakeAlphaDriver implements RecipeDiscoveryDriver
{
    public static ?int $requested = null;

    public function discover(int $n): array
    {
        self::$requested = $n;

        return [['title' => 'Alpha One'], ['title' => 'Alpha Two']];
    }
}

class FakeBetaDriver implements RecipeDiscoveryDriver
{
    public function discover(int $n): array
    {
        return [['title' => 'Beta One']];
    }
}

class ExplodingDriver implements RecipeDiscoveryDriver
{
    public function discover(int $n): array
    {
        throw new RuntimeException('driver blew up');
    }
}

it('aggregates candidates across all configured drivers', function () {
    config()->set('mealboard.drivers', [FakeAlphaDriver::class, FakeBetaDriver::class]);

    $result = app(RunDiscovery::class)->handle();

    expect(array_column($result['candidates'], 'title'))
        ->toBe(['Alpha One', 'Alpha Two', 'Beta One'])
        ->and($result['errors'])->toBe([]);

    // Each driver is asked for the configured candidates_per_run.
    expect(FakeAlphaDriver::$requested)->toBe((int) config('mealboard.candidates_per_run'));

    expect(DiscoveryRun::count())->toBe(2)
        ->and(DiscoveryRun::where('driver', FakeAlphaDriver::class)->first()->candidates_found)->toBe(2)
        ->and(DiscoveryRun::where('driver', FakeBetaDriver::class)->first()->error)->toBeNull();
});

it('records a throwing driver without killing the others', function () {
    config()->set('mealboard.drivers', [ExplodingDriver::class, FakeBetaDriver::class]);

    $result = app(RunDiscovery::class)->handle();

    expect(array_column($result['candidates'], 'title'))->toBe(['Beta One'])
        ->and($result['errors'])->toBe([ExplodingDriver::class => 'driver blew up']);

    $failed = DiscoveryRun::where('driver', ExplodingDriver::class)->first();

    expect($failed->error)->toBe('driver blew up')
        ->and($failed->candidates_found)->toBe(0)
        ->and(DiscoveryRun::where('driver', FakeBetaDriver::class)->first()->error)->toBeNull();
});
