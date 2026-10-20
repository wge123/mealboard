<?php

namespace App\Support;

/**
 * Small text helpers shared by the code that reads recipe lines and pages.
 */
final class Text
{
    /** Trim the text and turn every run of whitespace into one space. */
    public static function collapseWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
