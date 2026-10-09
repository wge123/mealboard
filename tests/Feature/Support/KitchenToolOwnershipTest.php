<?php

use App\Models\KitchenToolKind;
use App\Support\KitchenToolInventory;

it('marks a kind owned and not owned', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setOwned('wok', true);
    expect($inventory->owns('wok'))->toBeTrue();

    $inventory->setOwned('wok', false);
    expect($inventory->owns('wok'))->toBeFalse();
});

it('matches the kind name trimmed and lowercased when setting owned', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setOwned('  Wok ', true);

    expect($inventory->owns('wok'))->toBeTrue();
});

it('rejects an unknown kind when setting owned', function () {
    (new KitchenToolInventory)->setOwned('tortilla press', true);
})->throws(InvalidArgumentException::class);

it('saves, edits and clears a note', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setNote('skillet', '12-inch, cast iron');
    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBe('12-inch, cast iron');

    $inventory->setNote('skillet', '  10-inch  ');
    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBe('10-inch');

    $inventory->setNote('skillet', null);
    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBeNull();
});

it('stores an empty or blank note as no note', function () {
    $inventory = new KitchenToolInventory;
    $inventory->setNote('skillet', 'cast iron');

    $inventory->setNote('skillet', '   ');

    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBeNull();
});

it('rejects an unknown kind when setting a note', function () {
    (new KitchenToolInventory)->setNote('tortilla press', 'x');
})->throws(InvalidArgumentException::class);

it('does not let a note affect owning', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setNote('wok', 'owned');

    expect($inventory->owns('wok'))->toBeFalse();
});
