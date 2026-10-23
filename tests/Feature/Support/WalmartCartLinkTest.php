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

it('accepts only https walmart.com product urls', function (string $url, bool $ok) {
    expect(app(WalmartCartLink::class)->isProductUrl($url))->toBe($ok);
})->with([
    'www product' => ['https://www.walmart.com/ip/yellow-onion/44390949', true],
    'bare host' => ['https://walmart.com/ip/yellow-onion/44390949', true],
    'no slug' => ['https://www.walmart.com/ip/44390949', true],
    'tracking query' => ['https://www.walmart.com/ip/flour/44390949?athbdg=L1600', true],
    'javascript scheme' => ['javascript://x/%0aalert(1)//123', false],
    'javascript on walmart host' => ['javascript://www.walmart.com/ip/x/123', false],
    'http' => ['http://www.walmart.com/ip/x/123', false],
    'other host' => ['https://evil.example/ip/x/999', false],
    'lookalike suffix' => ['https://www.walmart.com.evil.example/ip/x/999', false],
    'lookalike prefix' => ['https://evilwalmart.com/ip/x/999', false],
    'subdomain' => ['https://affil.walmart.com/ip/x/999', false],
    'userinfo trick' => ['https://www.walmart.com@evil.example/ip/x/999', false],
    'userinfo on walmart' => ['https://user:pw@www.walmart.com/ip/x/999', false],
    'backslash trick' => ['https://evil.example\\@www.walmart.com/ip/x/999', false],
    'odd port' => ['https://www.walmart.com:8443/ip/x/999', false],
    'search page' => ['https://www.walmart.com/search?q=peas', false],
    'not under ip' => ['https://www.walmart.com/cart/123', false],
    'item id only in query' => ['https://www.walmart.com/ip/x?id=123', false],
    'newline' => ["https://www.walmart.com/ip/x/123\n", false],
    'embedded control char' => ["https://www.walmart.com/ip/x/12\n3", false],
    'space' => ['https://www.walmart.com/ip/x y/123', false],
    'not a url' => ['not a url', false],
    'empty' => ['', false],
]);
