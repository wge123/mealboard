<?php

use App\Actions\Planning\RecordCartAvailability;
use App\Enums\WalmartAvailability;
use App\Models\Ingredient;
use App\Models\WalmartMatch;
use Illuminate\Support\Carbon;

function cartMatch(string $ingredient, string $productName, string $productUrl): WalmartMatch
{
    return WalmartMatch::factory()->create([
        'ingredient_id' => Ingredient::factory()->create(['name' => $ingredient])->id,
        'product_name' => $productName,
        'product_url' => $productUrl,
    ]);
}

it('flags the 2026-10-05 misses and marks the rest ok', function () {
    $sprouts = cartMatch('bean sprouts', 'Fresh Bean Sprouts, 1 lb', 'https://www.walmart.com/ip/Fresh-Bean-Sprouts-1-Lb/103909028');
    $cabbage = cartMatch('green cabbage', 'Organic Fresh Green Cabbage, Each', 'https://www.walmart.com/ip/Organic-Fresh-Green-Cabbage-Each/13424822');
    $sesame = cartMatch('sesame oil', 'Kikkoman 100% Pure Sesame Oil, 5 fl oz', 'https://www.walmart.com/ip/Kikkoman-100-Pure-Sesame-Oil-5-Fl-Oz/110322105');
    $semolina = cartMatch('semolina', "Bob's Red Mill Semolina Flour, 24 oz", 'https://www.walmart.com/ip/Bob-s-Red-Mill-Semolina-Flour-24-oz/464723057');
    $onion = cartMatch('onion', 'Fresh Whole Yellow Onions, Each', 'https://www.walmart.com/ip/fresh-whole-yellow-onions-each/44390949');
    $cumin = cartMatch('cumin', 'Great Value Ground Cumin, 4.5 oz', 'https://www.walmart.com/ip/Great-Value-Ground-Cumin-4-5-oz/564519710');

    $this->travelTo(Carbon::parse('2026-10-05 18:00'));

    $report = app(RecordCartAvailability::class)->handle(
        WalmartMatch::all(),
        "Unable to add to Cart\nFresh Bean Sprouts 1 lb\nOut of stock\nOrganic Green Cabbage\n\$2.97",
        "Shipping\nKikkoman 100% Pure Sesame Oil 5 fl oz\nSold by KoFe\nFulfilled by Walmart\nArrives tomorrow\nBob's Red Mill Semolina Flour 24 oz\nSold and shipped by iHerb\n1 lb",
    );

    expect($report)->toBe([
        'out-of-stock' => ['Fresh Bean Sprouts, 1 lb', 'Organic Fresh Green Cabbage, Each'],
        'ship-only' => ['Kikkoman 100% Pure Sesame Oil, 5 fl oz', "Bob's Red Mill Semolina Flour, 24 oz"],
        'ok' => 2,
    ]);

    expect($sprouts->refresh()->availability)->toBe(WalmartAvailability::OutOfStock)
        ->and($cabbage->refresh()->availability)->toBe(WalmartAvailability::OutOfStock)
        ->and($sesame->refresh()->availability)->toBe(WalmartAvailability::ShipOnly)
        ->and($semolina->refresh()->availability)->toBe(WalmartAvailability::ShipOnly)
        ->and($onion->refresh()->availability)->toBe(WalmartAvailability::Ok)
        ->and($cumin->refresh()->availability)->toBe(WalmartAvailability::Ok)
        ->and($onion->availability_seen_at->toDateTimeString())->toBe('2026-10-05 18:00:00');
});

it('matches a line by the URL slug when the stored name is shorter', function () {
    $feta = cartMatch('feta', 'Athenos Feta', 'https://www.walmart.com/ip/Athenos-Traditional-Crumbled-Feta-Cheese-4-oz-Tub-Refrigerated/10308725');

    app(RecordCartAvailability::class)->handle(
        WalmartMatch::all(),
        'Athenos Traditional Crumbled Feta Cheese, 4 oz Tub, Refrigerated',
        '',
    );

    expect($feta->refresh()->availability)->toBe(WalmartAvailability::OutOfStock);
});

it('does not flag a sibling product that only shares the brand words', function () {
    $cumin = cartMatch('cumin', 'Great Value Ground Cumin, 4.5 oz', 'https://www.walmart.com/ip/Great-Value-Ground-Cumin-4-5-oz/564519710');

    app(RecordCartAvailability::class)->handle(
        WalmartMatch::all(),
        'Great Value Ground Ginger, 1.5 oz',
        '',
    );

    expect($cumin->refresh()->availability)->toBe(WalmartAvailability::Ok);
});

it('flags every ingredient that shares the missing product', function () {
    $url = 'https://www.walmart.com/ip/Garlic-Bulb-Fresh-Whole-Each/44391100';
    $garlic = cartMatch('garlic', 'Garlic Bulb Fresh Whole, Each', $url);
    $cloves = cartMatch('garlic cloves', 'Garlic Bulb Fresh Whole, Each', $url);

    $report = app(RecordCartAvailability::class)->handle(WalmartMatch::all(), 'Garlic Bulb Fresh Whole, Each', '');

    expect($report['out-of-stock'])->toBe(['Garlic Bulb Fresh Whole, Each'])
        ->and($garlic->refresh()->availability)->toBe(WalmartAvailability::OutOfStock)
        ->and($cloves->refresh()->availability)->toBe(WalmartAvailability::OutOfStock);
});

it('lets out of stock win over ship-only for the same product', function () {
    $feta = cartMatch('feta', 'Athenos Traditional Crumbled Feta, 4 oz', 'https://www.walmart.com/ip/Athenos-Feta/10308725');

    app(RecordCartAvailability::class)->handle(WalmartMatch::all(), 'Athenos Traditional Crumbled Feta 4 oz', 'Athenos Traditional Crumbled Feta 4 oz');

    expect($feta->refresh()->availability)->toBe(WalmartAvailability::OutOfStock);
});

it('leaves matches outside the cart untouched', function () {
    $carted = cartMatch('onion', 'Fresh Whole Yellow Onions, Each', 'https://www.walmart.com/ip/onions/44390949');
    $other = cartMatch('feta', 'Athenos Traditional Crumbled Feta, 4 oz', 'https://www.walmart.com/ip/feta/10308725');

    app(RecordCartAvailability::class)->handle(WalmartMatch::whereKey($carted->id)->get(), 'Athenos Traditional Crumbled Feta 4 oz', '');

    expect($carted->refresh()->availability)->toBe(WalmartAvailability::Ok)
        ->and($other->refresh()->availability)->toBeNull();
});
