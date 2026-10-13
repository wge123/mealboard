<?php

namespace App\Support;

use App\Models\Recipe;

/**
 * The generated cover behind a recipe without a picture: its meal colour
 * washed with a hue taken from the title, so covers tell apart without
 * photos, and the title's first two initials to sit on it.
 */
class RecipeCover
{
    public static function background(Recipe $recipe): string
    {
        $hue = crc32($recipe->title) % 360;
        $shifted = ($hue + 40) % 360;

        return "background: radial-gradient(circle at 25% 20%, oklch(85% 0.09 {$hue} / .9), transparent 60%), "
            ."linear-gradient(135deg, color-mix(in oklab, var(--meal-{$recipe->meal_type->value}) 55%, var(--color-base-100)), oklch(75% 0.08 {$shifted}))";
    }

    public static function initials(Recipe $recipe): string
    {
        return collect(preg_split('/\s+/', $recipe->title))
            ->filter(fn ($word) => preg_match('/^\p{L}/u', $word))
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }
}
