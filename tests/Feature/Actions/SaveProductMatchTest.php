<?php

use App\Actions\Planning\SaveProductMatch;
use App\Enums\WalmartAvailability;
use App\Models\Ingredient;
use App\Models\WalmartMatch;
use Illuminate\Support\Carbon;

it('creates a match for an unmapped ingredient', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion']);

    $this->travelTo(Carbon::parse('2026-07-20 10:00'));

    $match = app(SaveProductMatch::class)->handle(
        $onion,
        'https://www.walmart.com/ip/onion/123',
        'Yellow Onion, each',
    );

    expect($match->ingredient_id)->toBe($onion->id)
        ->and($match->product_url)->toBe('https://www.walmart.com/ip/onion/123')
        ->and($match->product_name)->toBe('Yellow Onion, each')
        ->and($match->last_confirmed_at->toDateTimeString())->toBe('2026-07-20 10:00:00');
});

it('updates the existing match instead of duplicating and refreshes the stamp', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion']);

    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/old/1',
        'product_name' => 'Old Onion',
        'last_confirmed_at' => Carbon::parse('2026-01-01 00:00'),
    ]);

    $this->travelTo(Carbon::parse('2026-07-20 10:00'));

    app(SaveProductMatch::class)->handle(
        $onion,
        'https://www.walmart.com/ip/new/2',
        'New Onion',
    );

    expect(WalmartMatch::count())->toBe(1);

    $match = WalmartMatch::sole();

    expect($match->product_url)->toBe('https://www.walmart.com/ip/new/2')
        ->and($match->product_name)->toBe('New Onion')
        ->and($match->last_confirmed_at->toDateTimeString())->toBe('2026-07-20 10:00:00');
});

it('keeps matches per ingredient independent', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion']);
    $garlic = Ingredient::factory()->create(['name' => 'garlic']);

    app(SaveProductMatch::class)->handle($onion, 'https://www.walmart.com/ip/onion/1', 'Onion');
    app(SaveProductMatch::class)->handle($garlic, 'https://www.walmart.com/ip/garlic/2', 'Garlic');

    expect(WalmartMatch::count())->toBe(2)
        ->and($onion->walmartMatch->product_url)->toBe('https://www.walmart.com/ip/onion/1')
        ->and($garlic->walmartMatch->product_url)->toBe('https://www.walmart.com/ip/garlic/2');
});

it('clears the availability flag when the product changes', function () {
    $sprouts = Ingredient::factory()->create(['name' => 'bean sprouts']);

    WalmartMatch::factory()->create([
        'ingredient_id' => $sprouts->id,
        'product_url' => 'https://www.walmart.com/ip/old/1',
        'availability' => WalmartAvailability::OutOfStock,
        'availability_seen_at' => now(),
    ]);

    app(SaveProductMatch::class)->handle($sprouts, 'https://www.walmart.com/ip/new/2', 'New Sprouts');

    $match = WalmartMatch::sole();

    expect($match->availability)->toBeNull()
        ->and($match->availability_seen_at)->toBeNull();
});

it('keeps the availability flag when the same product is re-confirmed', function () {
    $sprouts = Ingredient::factory()->create(['name' => 'bean sprouts']);

    WalmartMatch::factory()->create([
        'ingredient_id' => $sprouts->id,
        'product_url' => 'https://www.walmart.com/ip/same/1',
        'availability' => WalmartAvailability::ShipOnly,
        'availability_seen_at' => now(),
    ]);

    app(SaveProductMatch::class)->handle($sprouts, 'https://www.walmart.com/ip/same/1', 'Same Sprouts');

    expect(WalmartMatch::sole()->availability)->toBe(WalmartAvailability::ShipOnly);
});
