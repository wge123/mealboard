<?php

namespace App\Discovery;

use App\Models\Recipe;

/**
 * Fuzzy title dedupe shared by scheduled discovery and the on-demand
 * requester. Rejected recipes are deliberately part of the known set, so a
 * suggestion the household already turned down is never offered again.
 */
class NearDuplicateFilter
{
    /** Edit distance at or under which two titles are the same recipe. */
    private const int MAX_EDIT_DISTANCE = 3;

    /** Similarity percentage at or above which two titles are the same recipe. */
    private const int MIN_SIMILARITY_PERCENT = 85;

    /**
     * Every existing recipe title, lowercased and trimmed, ready to seed a
     * run's known set.
     *
     * @return list<string>
     */
    public function knownTitles(): array
    {
        return Recipe::query()
            ->pluck('title')
            ->map(fn (string $title) => mb_strtolower(trim($title)))
            ->all();
    }

    /**
     * @param  list<string>  $knownTitles  lowercased, trimmed
     */
    public function isDuplicate(string $title, array $knownTitles): bool
    {
        $needle = mb_strtolower(trim($title));

        foreach ($knownTitles as $known) {
            if (levenshtein($needle, $known) <= self::MAX_EDIT_DISTANCE) {
                return true;
            }

            similar_text($needle, $known, $percent);

            if ($percent >= self::MIN_SIMILARITY_PERCENT) {
                return true;
            }
        }

        return false;
    }
}
