<?php

namespace App\Discovery;

use App\Enums\RecipePageRefusal;

/**
 * What the page reader found: a candidate in the recipe shape, or a named
 * refusal, never both and never neither (the constructor is private). A
 * candidate comes with the page's own canonical address when the page names one.
 */
final readonly class RecipePageReading
{
    /**
     * @param  ?array<string, mixed>  $candidate
     */
    private function __construct(
        public ?array $candidate,
        public ?RecipePageRefusal $refusal,
        public ?string $canonicalUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $candidate
     * @param  ?string  $canonicalUrl  the address the page names itself by, when it says
     */
    public static function of(array $candidate, ?string $canonicalUrl = null): self
    {
        return new self($candidate, null, $canonicalUrl);
    }

    public static function refused(RecipePageRefusal $refusal): self
    {
        return new self(null, $refusal);
    }
}
