<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The picture shown on a kitchen tool's tile: an inline stroke icon from
 * resources/svg/kitchen-tools (Tabler and Lucide, licences beside them),
 * matched on a word in the tool's name. The first match wins, so longer words
 * sit before the shorter words they contain. A tool with no fitting picture
 * gets the plain utensils icon.
 */
class KitchenToolIcon
{
    private const string FALLBACK = 'tools-kitchen-2';

    private const array ICONS = [
        'paring knife' => 'pocket-knife', 'knife' => 'slice',
        'grater' => 'cheese', 'peeler' => 'carrot', 'mortar' => 'bowl-spoon', 'scale' => 'scale-outline',
        'measuring cups' => 'mug', 'measuring spoons' => 'ruler-measure', 'thermometer' => 'thermometer',
        'mixing bowls' => 'bowl', 'colander' => 'basket', 'rolling pin' => 'baguette', 'can opener' => 'cylinder',
        'toaster oven' => 'toaster-oven', 'dutch oven' => 'cooking-pot', 'microwave' => 'microwave',
        'oven' => 'cooker', 'air fryer' => 'cooker', 'stovetop' => 'flame', 'wok' => 'wok',
        'stockpot' => 'soup', 'saucepan' => 'soup', 'pots' => 'cooking-pot',
        'pressure cooker' => 'steam', 'steamer' => 'steam', 'rice cooker' => 'bowl-chopsticks', 'slow cooker' => 'slow-cooker',
        'griddle' => 'grill-spatula', 'spatula' => 'grill-spatula', 'grill' => 'grill', 'tongs' => 'grill-fork',
        'cake' => 'cake', 'loaf' => 'bread', 'muffin' => 'cake-slice', 'pie' => 'pizza', 'sheet pan' => 'cookie',
        'baking dish' => 'beef', 'roasting' => 'ham', 'skillet' => 'egg-fried', 'wire rack' => 'grid-pattern', 'waffle' => 'grid-3x3',
        'blender' => 'blender', 'processor' => 'blender', 'stand mixer' => 'bowl', 'mixer' => 'whisk', 'whisk' => 'whisk',
        'ladle' => 'ladle', 'spoon' => 'tools-kitchen-3',
    ];

    /** @var array<string, HtmlString> */
    private static array $svgs = [];

    /**
     * The icon's file name, without .svg.
     */
    public static function name(string $tool): string
    {
        foreach (self::ICONS as $word => $icon) {
            if (str_contains($tool, $word)) {
                return $icon;
            }
        }

        return self::FALLBACK;
    }

    public static function for(string $tool): HtmlString
    {
        $name = self::name($tool);

        return self::$svgs[$name] ??= new HtmlString(file_get_contents(resource_path("svg/kitchen-tools/{$name}.svg")));
    }
}
