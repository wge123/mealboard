<?php

use App\Models\KitchenToolKind;
use App\Support\KitchenToolIcon;

it('draws every catalog kitchen tool as an inline stroke icon', function () {
    foreach (KitchenToolKind::query()->pluck('name') as $name) {
        expect((string) KitchenToolIcon::for($name))->toStartWith('<svg')->toContain('stroke="currentColor"');
    }
});

it('picks the icon that shows what the tool is', function (string $tool, string $icon) {
    expect(KitchenToolIcon::name($tool))->toBe($icon);
})->with([
    ['microwave', 'microwave'],
    ['spatula', 'grill-spatula'],
    ['whisk', 'whisk'],
    ["chef's knife", 'slice'],
    ['paring knife', 'pocket-knife'],
    ['dutch oven', 'cooking-pot'],
    ['oven', 'cooker'],
    ['toaster oven', 'toaster-oven'],
    ['wire rack', 'grid-pattern'],
    ['waffle iron', 'grid-3x3'],
]);

it('gives a tool it has no picture for the plain utensils icon', function () {
    expect(KitchenToolIcon::name('tortilla press'))->toBe('tools-kitchen-2');
});
