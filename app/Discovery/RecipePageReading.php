<?php

namespace App\Discovery;

use App\Enums\RecipePageRefusal;

/**
 * What the page reader found: a candidate in the recipe shape, or a named refusal.
 */
final readonly class RecipePageReading
{
    /**
     * @param  ?array<string, mixed>  $candidate
     */
    public function __construct(
        public ?array $candidate,
        public ?RecipePageRefusal $refusal = null,
    ) {}
}
