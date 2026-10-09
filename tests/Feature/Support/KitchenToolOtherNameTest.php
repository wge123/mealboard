<?php

use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\KitchenToolOtherName;
use App\Support\KitchenToolInventory;

it('resolves a household other name once added', function () {
    $inventory = new KitchenToolInventory;
    $skillet = $inventory->resolve('skillet');

    $otherName = $inventory->addOtherName($skillet, '  Big Pan ');

    expect($otherName->name)->toBe('big pan')
        ->and($otherName->origin)->toBe(KitchenToolKind::ORIGIN_HOUSEHOLD)
        ->and($inventory->resolve('Big Pan')->name)->toBe('skillet');
});

it('refuses an other name that is already a kind name', function () {
    $inventory = new KitchenToolInventory;

    $inventory->addOtherName($inventory->resolve('skillet'), 'Wok');
})->throws(KitchenToolRefused::class);

it('refuses an other name that is already another kind\'s other name', function () {
    $inventory = new KitchenToolInventory;

    $inventory->addOtherName($inventory->resolve('wok'), 'frying pan');
})->throws(KitchenToolRefused::class);

it('refuses a blank other name', function () {
    $inventory = new KitchenToolInventory;

    $inventory->addOtherName($inventory->resolve('skillet'), '   ');
})->throws(KitchenToolRefused::class);

it('removes a household other name', function () {
    $inventory = new KitchenToolInventory;
    $otherName = $inventory->addOtherName($inventory->resolve('skillet'), 'big pan');

    $inventory->removeOtherName($otherName);

    expect($inventory->resolve('big pan'))->toBeNull();
});

it('refuses to remove a catalog other name', function () {
    $inventory = new KitchenToolInventory;
    $catalog = KitchenToolOtherName::query()->where('name', 'frying pan')->firstOrFail();

    try {
        $inventory->removeOtherName($catalog);
    } finally {
        expect($inventory->resolve('frying pan'))->not->toBeNull();
    }
})->throws(KitchenToolRefused::class);
