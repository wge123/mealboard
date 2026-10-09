<?php

use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\KitchenToolOtherName;
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

it('resolves a word by kind name and by catalog other name', function () {
    $inventory = new KitchenToolInventory;

    expect($inventory->resolve('skillet')->name)->toBe('skillet')
        ->and($inventory->resolve('frying pan')->name)->toBe('skillet');
});

it('resolves ignoring case and surrounding spaces', function () {
    $inventory = new KitchenToolInventory;

    expect($inventory->resolve('  Frying Pan '))->not->toBeNull()
        ->and($inventory->resolve(' WOK ')->name)->toBe('wok');
});

it('resolves nothing for an unknown word or a note text', function () {
    $inventory = new KitchenToolInventory;
    KitchenToolKind::query()->where('name', 'wok')->update(['note' => 'carbon steel thing']);

    expect($inventory->resolve('tortilla press'))->toBeNull()
        ->and($inventory->resolve('carbon steel thing'))->toBeNull()
        ->and($inventory->resolve('   '))->toBeNull();
});

it('does not fold plurals when resolving', function () {
    expect((new KitchenToolInventory)->resolve('skillets'))->toBeNull();
});

it('adds a household kind that is owned and listed', function () {
    $inventory = new KitchenToolInventory;

    $kind = $inventory->addKind('  Tortilla Press ');

    expect($kind->name)->toBe('tortilla press')
        ->and($kind->origin)->toBe(KitchenToolKind::ORIGIN_HOUSEHOLD)
        ->and($inventory->owns('tortilla press'))->toBeTrue()
        ->and($inventory->kinds())->toContain('tortilla press')
        ->and($inventory->resolve('Tortilla Press')->is($kind))->toBeTrue();
});

it('refuses to add a kind whose word already resolves', function (string $word) {
    (new KitchenToolInventory)->addKind($word);
})->with(['skillet', 'Frying Pan', ' WOK '])->throws(KitchenToolRefused::class);

it('refuses to add an empty kind name', function () {
    (new KitchenToolInventory)->addKind('   ');
})->throws(KitchenToolRefused::class);

it('refuses to add the same household kind twice', function () {
    $inventory = new KitchenToolInventory;
    $inventory->addKind('tortilla press');

    $inventory->addKind('Tortilla press');
})->throws(KitchenToolRefused::class);

it('deletes a household kind together with its other names', function () {
    $inventory = new KitchenToolInventory;
    $kind = $inventory->addKind('tortilla press');
    KitchenToolOtherName::create([
        'kitchen_tool_kind_id' => $kind->id,
        'name' => 'masa press',
        'origin' => KitchenToolKind::ORIGIN_HOUSEHOLD,
    ]);

    $inventory->deleteKind($kind);

    expect($inventory->resolve('tortilla press'))->toBeNull()
        ->and($inventory->resolve('masa press'))->toBeNull()
        ->and(KitchenToolOtherName::query()->where('name', 'masa press')->exists())->toBeFalse();
});

it('refuses to delete a catalog kind', function () {
    $inventory = new KitchenToolInventory;

    try {
        $inventory->deleteKind($inventory->resolve('skillet'));
    } finally {
        expect($inventory->resolve('frying pan'))->not->toBeNull();
    }
})->throws(KitchenToolRefused::class);
