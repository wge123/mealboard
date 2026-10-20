<?php

namespace App\Discovery;

use App\Support\KitchenToolInventory;

/**
 * Reads the kitchen tools off a recipe's steps by code: every known kind
 * name or other name that appears as a whole word. The longest word wins
 * ("Dutch oven" is not also "oven"), a kind is listed once, and two tools
 * joined by "or" ("a large pot or Dutch oven") are alternatives of one entry.
 */
class KitchenToolsInSteps
{
    public function __construct(private KitchenToolInventory $inventory) {}

    /**
     * @param  list<string>  $steps
     * @return list<array{alternatives: list<string>, count: int}>
     */
    public function find(array $steps): array
    {
        $words = $this->inventory->words();
        usort($words, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        if ($words === []) {
            return [];
        }

        $pattern = '/(?<![\p{L}\p{N}])('.implode('|', array_map(fn (string $w) => preg_quote($w, '/'), $words)).')(?![\p{L}\p{N}])/iu';
        $tools = [];
        $seen = [];

        foreach ($steps as $step) {
            if (! preg_match_all($pattern, $step, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $previousEnd = null;
            $current = null;

            foreach ($matches[1] as [$found, $offset]) {
                $word = mb_strtolower($found);
                $joined = $previousEnd !== null && substr($step, $previousEnd, $offset - $previousEnd) === ' or ';
                $previousEnd = $offset + strlen($found);
                $kind = $this->inventory->resolve($word)?->id;

                if ($kind !== null && isset($seen[$kind])) {
                    continue;
                }

                if ($kind !== null) {
                    $seen[$kind] = true;
                }

                if ($joined && $current !== null) {
                    $tools[$current]['alternatives'][] = $word;
                } else {
                    $tools[] = ['alternatives' => [$word], 'count' => 1];
                    $current = array_key_last($tools);
                }
            }
        }

        return array_values($tools);
    }
}
