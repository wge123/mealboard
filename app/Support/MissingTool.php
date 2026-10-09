<?php

namespace App\Support;

use App\Models\KitchenToolKind;
use App\Models\RecipeTool;

/**
 * One recipe tool entry the household cannot cover: none of its alternatives
 * is an owned kind. Carries each alternative's word and kind (null when the
 * word is unknown) so callers can offer actions per word.
 */
final readonly class MissingTool
{
    /**
     * @param  list<array{word: string, kind: ?KitchenToolKind}>  $alternatives
     */
    public function __construct(
        public RecipeTool $tool,
        public array $alternatives,
    ) {}

    /** The alternatives' words joined by "or". */
    public function label(): string
    {
        return implode(' or ', array_column($this->alternatives, 'word'));
    }
}
