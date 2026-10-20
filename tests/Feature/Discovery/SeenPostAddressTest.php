<?php

use App\Discovery\RecipeSiteDriver;
use App\Models\RecipeSite;
use App\Models\SeenPost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function longPostAddress(string $ending): string
{
    return 'https://long.example/'.str_repeat('a-long-slug/', 30).$ending;
}

it('stores the post address as text, so an address longer than 255 characters fits', function () {
    $column = collect(Schema::getColumns('seen_posts'))->firstWhere('name', 'url');

    expect($column['type_name'])->toBeIn(['text', 'longtext'])
        ->and(Schema::hasColumn('seen_posts', 'url_hash'))->toBeTrue();
});

it('keeps a sha256 hash of the address, unique per address', function () {
    $address = longPostAddress('one/');

    $post = SeenPost::create(['url' => $address]);

    expect($post->url_hash)->toBe(hash('sha256', $address))
        ->and(fn () => SeenPost::create(['url' => $address]))->toThrow(UniqueConstraintViolationException::class);
});

it('tells a seen address from an unseen one by its hash, even when two share their first 255 characters', function () {
    SeenPost::create(['url' => longPostAddress('one/')]);

    expect(SeenPost::seenAmong([longPostAddress('one/'), longPostAddress('two/')]))->toBe([longPostAddress('one/')]);
});

it('does not fetch a long-addressed post a second time', function () {
    config()->set('mealboard.drivers', [RecipeSiteDriver::class]);
    RecipeSite::query()->delete();
    RecipeSite::factory()->create(['feed_url' => 'https://long.example/feed/', 'name' => 'Long']);
    $address = longPostAddress('post/');
    Http::fake([
        'https://long.example/feed/' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><title>L</title><item><link>'.$address.'</link></item></channel></rss>'),
        $address => Http::response('<html><body>An article.</body></html>'),
    ]);

    $this->artisan('recipes:discover')->assertSuccessful();
    $this->artisan('recipes:discover', ['--force' => true])->assertSuccessful();

    expect(count(Http::recorded(fn ($request) => $request->url() === $address)))->toBe(1);
});
