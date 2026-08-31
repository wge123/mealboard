<?php

use App\Support\WalmartCartLink;

it('parses the usItemId off a canonical product URL', function () {
    expect(app(WalmartCartLink::class)->itemId('https://www.walmart.com/ip/yellow-onion/44390949'))
        ->toBe('44390949');
});

it('parses the usItemId through a trailing slash and a query string', function () {
    $link = app(WalmartCartLink::class);

    expect($link->itemId('https://www.walmart.com/ip/yellow-onion/44390949/'))->toBe('44390949')
        ->and($link->itemId('https://www.walmart.com/ip/yellow-onion/44390949?athbdg=L1600'))->toBe('44390949');
});

it('parses a slugless product URL', function () {
    expect(app(WalmartCartLink::class)->itemId('https://www.walmart.com/ip/363472942'))
        ->toBe('363472942');
});

it('returns null for a URL that carries no item id', function () {
    $link = app(WalmartCartLink::class);

    expect($link->itemId('https://www.walmart.com/search?q=yellow+onion'))->toBeNull()
        ->and($link->itemId('https://www.walmart.com/ip/yellow-onion'))->toBeNull()
        ->and($link->itemId('not a url'))->toBeNull();
});

it('joins matched product URLs into one affiliate add-to-cart link', function () {
    expect(app(WalmartCartLink::class)->handle([
        'https://www.walmart.com/ip/yellow-onion/44390949',
        'https://www.walmart.com/ip/salt/10315356',
    ]))->toBe('https://affil.walmart.com/cart/addToCart?items=44390949,10315356');
});

it('dedupes repeated item ids', function () {
    expect(app(WalmartCartLink::class)->handle([
        'https://www.walmart.com/ip/flour/44390949',
        'https://www.walmart.com/ip/flour/44390949?athbdg=L1600',
    ]))->toBe('https://affil.walmart.com/cart/addToCart?items=44390949');
});

it('skips URLs with no item id rather than emitting an empty slot', function () {
    expect(app(WalmartCartLink::class)->handle([
        'https://www.walmart.com/search?q=peas',
        'https://www.walmart.com/ip/salt/10315356',
    ]))->toBe('https://affil.walmart.com/cart/addToCart?items=10315356');
});

it('returns null rather than a bare cart URL when nothing is matched', function () {
    $link = app(WalmartCartLink::class);

    expect($link->handle([]))->toBeNull()
        ->and($link->handle(['https://www.walmart.com/search?q=peas']))->toBeNull();
});
