<?php

use App\Actions\Planning\ProposeProductMatch;
use App\Actions\Planning\SaveProductMatch;
use App\Models\Ingredient;
use App\Models\WalmartMatch;
use App\Models\WalmartMatchProposal;
use App\Models\WalmartRejectedItem;

it('parks a guess as a proposal, not a match', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);

    $proposal = app(ProposeProductMatch::class)->handle(
        $cilantro,
        'https://www.walmart.com/ip/fresh-cilantro/160597260',
        'Fresh Cilantro, 1 bunch',
        'medium',
    );

    expect($proposal->ingredient_id)->toBe($cilantro->id)
        ->and($proposal->confidence)->toBe('medium')
        ->and(WalmartMatch::count())->toBe(0);
});

it('replaces the pending proposal instead of stacking a second one', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);
    $propose = app(ProposeProductMatch::class);

    $propose->handle($cilantro, 'https://www.walmart.com/ip/a/111', 'A');
    $propose->handle($cilantro, 'https://www.walmart.com/ip/b/222', 'B');

    expect(WalmartMatchProposal::sole()->product_name)->toBe('B');
});

it('never proposes an item id rejected for that ingredient', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);
    WalmartRejectedItem::create(['ingredient_id' => $cilantro->id, 'item_id' => '160597260']);

    app(ProposeProductMatch::class)->handle($cilantro, 'https://www.walmart.com/ip/renamed-slug/160597260', 'Cilantro');
})->throws(DomainException::class, 'Item 160597260 was rejected for cilantro');

it('still proposes an id rejected for a different ingredient', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);
    $parsley = Ingredient::factory()->create(['name' => 'parsley']);
    WalmartRejectedItem::create(['ingredient_id' => $parsley->id, 'item_id' => '160597260']);

    app(ProposeProductMatch::class)->handle($cilantro, 'https://www.walmart.com/ip/c/160597260', 'Cilantro');

    expect(WalmartMatchProposal::count())->toBe(1);
});

it('refuses an ingredient that already has a confirmed match', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);
    WalmartMatch::factory()->create(['ingredient_id' => $cilantro->id]);

    app(ProposeProductMatch::class)->handle($cilantro, 'https://www.walmart.com/ip/c/1', 'Cilantro');
})->throws(DomainException::class, 'already has a confirmed match');

it('refuses a url with no item id', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);

    app(ProposeProductMatch::class)->handle($cilantro, 'https://www.walmart.com/search?q=cilantro', 'Cilantro');
})->throws(DomainException::class, 'No Walmart item id');

it('clears a pending proposal when a match is confirmed', function () {
    $cilantro = Ingredient::factory()->create(['name' => 'cilantro']);
    WalmartMatchProposal::factory()->create(['ingredient_id' => $cilantro->id]);

    app(SaveProductMatch::class)->handle($cilantro, 'https://www.walmart.com/ip/c/9', 'Cilantro');

    expect(WalmartMatchProposal::count())->toBe(0)
        ->and(WalmartMatch::count())->toBe(1);
});
