<?php

namespace App\Support;

use App\Actions\Recipes\ParsePastedIngredients;

class CleanIngredientKeywords
{
    public function __construct(private ParsePastedIngredients $parser) {}

    /**
     * Reduce an ingredient line to search keywords: quantities, units, and
     * prep notes go, the bare name stays ("2 cups diced yellow onion" ->
     * "yellow onion"). Reuses the paste parser, which already knows the unit
     * aliases, prep words, and comma-note conventions.
     */
    public function handle(string $line): string
    {
        $rows = $this->parser->handle($line);

        return $rows === [] ? '' : $rows[0]['name'];
    }
}
