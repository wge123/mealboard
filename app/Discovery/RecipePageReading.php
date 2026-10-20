<?php

namespace App\Discovery;

use App\Enums\RecipePageRefusal;

/**
 * What the page reader found: a candidate in the recipe shape, or a named
 * refusal, never both and never neither (the constructor is private).
 */
final readonly class RecipePageReading
{
    /**
     * @param  ?array<string, mixed>  $candidate
     */
    private function __construct(
        public ?array $candidate,
        public ?RecipePageRefusal $refusal,
    ) {}

    /**
     * @param  array<string, mixed>  $candidate
     */
    public static function of(array $candidate): self
    {
        return new self($candidate, null);
    }

    public static function refused(RecipePageRefusal $refusal): self
    {
        return new self(null, $refusal);
    }
}
