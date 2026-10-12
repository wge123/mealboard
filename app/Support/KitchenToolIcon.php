<?php

namespace App\Support;

/**
 * The picture shown on a kitchen tool's tile, matched on a word in its name.
 * The first match wins, so longer words sit before the shorter words they contain.
 */
class KitchenToolIcon
{
    private const array ICONS = [
        'knife' => '🔪', 'board' => '🪵', 'grater' => '🧀', 'peeler' => '🥕', 'mortar' => '🫙', 'scale' => '⚖️',
        'measuring' => '🥄', 'bowls' => '🥣', 'colander' => '🍝', 'rolling' => '🥖', 'can opener' => '🥫',
        'toaster' => '🍞', 'dutch oven' => '🍲', 'oven' => '🔥', 'wok' => '🥡', 'stovetop' => '🔥', 'pot' => '🍲',
        'griddle' => '🥞', 'steamer' => '♨️', 'cake' => '🎂', 'loaf' => '🍞', 'muffin' => '🧁', 'pie' => '🥧',
        'rack' => '🧇', 'sheet' => '🍪', 'baking' => '🥘', 'pan' => '🍳', 'skillet' => '🍳', 'fryer' => '🍟',
        'blender' => '🥤', 'processor' => '⚙️', 'mixer' => '🌀', 'microwave' => '📡', 'rice' => '🍚', 'cooker' => '🍲',
        'waffle' => '🧇', 'thermometer' => '🌡️', 'ladle' => '🥄', 'spatula' => '🍳', 'tongs' => '🥢', 'whisk' => '🥚',
        'spoon' => '🥄',
    ];

    public static function for(string $name): string
    {
        foreach (self::ICONS as $word => $icon) {
            if (str_contains($name, $word)) {
                return $icon;
            }
        }

        return '🍴';
    }
}
