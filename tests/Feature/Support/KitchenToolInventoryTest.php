<?php

use App\Support\KitchenToolInventory;

it('starts owning the basics', function () {
    $inventory = new KitchenToolInventory;

    foreach (['oven', 'stovetop', "chef's knife", 'cutting board', 'pots', 'mixing bowls'] as $basic) {
        expect($inventory->owns($basic))->toBeTrue("expected {$basic} to be owned");
    }
});

it('knows the named catalog kinds', function () {
    $kinds = (new KitchenToolInventory)->kinds();

    expect($kinds)->toContain(
        'skillet', 'dutch oven', 'wok', 'sheet pan', 'flat-top griddle',
        'air fryer', 'blender', 'stand mixer', 'slow cooker', 'pressure cooker',
    );
});

it('does not own catalog kinds outside the basics', function () {
    expect((new KitchenToolInventory)->owns('wok'))->toBeFalse();
});

it('does not own a kind it does not know', function () {
    expect((new KitchenToolInventory)->owns('tortilla press'))->toBeFalse();
});
