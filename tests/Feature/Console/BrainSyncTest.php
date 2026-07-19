<?php

use App\Models\BrainNote;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('mealboard.github_pat', 'test-pat');
    config()->set('mealboard.brain_repo', 'wge123/second-brain-vault');
    config()->set('mealboard.brain_files', ['wiki/concepts/food-preferences.md']);
});

it('fetches configured vault files and stores them as brain notes', function () {
    Http::fake([
        'api.github.com/*' => Http::response("# Food preferences\n\nNo mushrooms. Loves spicy food."),
    ]);

    $this->artisan('brain:sync')
        ->expectsOutputToContain('wiki/concepts/food-preferences.md: synced.')
        ->assertSuccessful();

    $note = BrainNote::sole();

    expect($note->path)->toBe('wiki/concepts/food-preferences.md')
        ->and($note->content)->toContain('No mushrooms. Loves spicy food.')
        ->and($note->fetched_at)->not->toBeNull();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.github.com/repos/wge123/second-brain-vault/contents/wiki/concepts/food-preferences.md'
            && $request->hasHeader('Authorization', 'Bearer test-pat')
            && $request->hasHeader('Accept', 'application/vnd.github.raw+json');
    });
});

it('updates an existing note in place instead of duplicating it', function () {
    BrainNote::factory()->create([
        'path' => 'wiki/concepts/food-preferences.md',
        'content' => 'stale',
        'fetched_at' => now()->subWeek(),
    ]);

    Http::fake(['api.github.com/*' => Http::response('fresh content')]);

    $this->artisan('brain:sync')->assertSuccessful();

    $note = BrainNote::sole();

    expect($note->content)->toBe('fresh content')
        ->and($note->fetched_at->isToday())->toBeTrue();
});

it('warns and continues when a vault file is missing', function () {
    config()->set('mealboard.brain_files', [
        'wiki/concepts/missing.md',
        'wiki/concepts/food-preferences.md',
    ]);

    Http::fake([
        'api.github.com/repos/wge123/second-brain-vault/contents/wiki/concepts/missing.md' => Http::response('Not Found', 404),
        'api.github.com/repos/wge123/second-brain-vault/contents/wiki/concepts/food-preferences.md' => Http::response('present'),
    ]);

    $this->artisan('brain:sync')
        ->expectsOutputToContain('wiki/concepts/missing.md: not found in wge123/second-brain-vault — skipped.')
        ->assertSuccessful();

    expect(BrainNote::pluck('path')->all())->toBe(['wiki/concepts/food-preferences.md']);
});

it('throws when GITHUB_PAT is not set', function () {
    config()->set('mealboard.github_pat', null);

    Http::fake();

    expect(fn () => $this->artisan('brain:sync'))
        ->toThrow(RuntimeException::class, 'GITHUB_PAT not set');

    Http::assertNothingSent();
});
