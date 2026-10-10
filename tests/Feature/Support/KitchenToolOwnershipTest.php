<?php

use App\Models\KitchenToolKind;
use App\Support\KitchenToolInventory;

it('marks a kind owned and not owned', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setOwned(KitchenToolKind::where('name', 'wok')->firstOrFail(), true);
    expect($inventory->owns(KitchenToolKind::where('name', 'wok')->firstOrFail()))->toBeTrue();

    $inventory->setOwned(KitchenToolKind::where('name', 'wok')->firstOrFail(), false);
    expect($inventory->owns(KitchenToolKind::where('name', 'wok')->firstOrFail()))->toBeFalse();
});

it('saves, edits and clears a note', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setNote(KitchenToolKind::where('name', 'skillet')->firstOrFail(), '12-inch, cast iron');
    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBe('12-inch, cast iron');

    $inventory->setNote(KitchenToolKind::where('name', 'skillet')->firstOrFail(), '  10-inch  ');
    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBe('10-inch');

    $inventory->setNote(KitchenToolKind::where('name', 'skillet')->firstOrFail(), null);
    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBeNull();
});

it('stores an empty or blank note as no note', function () {
    $inventory = new KitchenToolInventory;
    $inventory->setNote(KitchenToolKind::where('name', 'skillet')->firstOrFail(), 'cast iron');

    $inventory->setNote(KitchenToolKind::where('name', 'skillet')->firstOrFail(), '   ');

    expect(KitchenToolKind::where('name', 'skillet')->value('note'))->toBeNull();
});

it('does not let a note affect owning', function () {
    $inventory = new KitchenToolInventory;

    $inventory->setNote(KitchenToolKind::where('name', 'wok')->firstOrFail(), 'owned');

    expect($inventory->owns(KitchenToolKind::where('name', 'wok')->firstOrFail()))->toBeFalse();
});
