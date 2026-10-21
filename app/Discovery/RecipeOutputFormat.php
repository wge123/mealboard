<?php

namespace App\Discovery;

/**
 * The one definition of the JSON a model is asked to produce for a recipe in
 * the recipe shape (tools with alternatives and count, ingredients each with
 * a prep_note and an optional prep_bowl, and steps). Every model prompt embeds schema(); the matching
 * check is CandidateValidator::check.
 */
final class RecipeOutputFormat
{
    /**
     * The object spec, ready to drop into a prompt. $sourceUrl describes the
     * source_url value, which differs per path (a video extraction injects
     * it itself).
     */
    public static function schema(string $sourceUrl): string
    {
        return <<<SCHEMA
        {
          "title": string,
          "description": string (1-2 sentences),
          "meal_type": "breakfast" | "lunch" | "dinner" | "any",
          "prep_minutes": integer,
          "cook_minutes": integer,
          "servings": integer,
          "cuisine": string or null,
          "tags": array of strings,
          "source_url": {$sourceUrl},
          "tools": array of {"alternatives": array of strings (one or more words for the same tool, e.g. ["flat-top griddle", "large skillet"]), "count": integer (how many are needed, 1 or more)},
          "ingredients": array of {"qty": number or null, "unit": string or null, "name": string, "prep_note": string or null (how it is prepared before cooking, e.g. "diced"), "prep_bowl": string or null (the bowl the recipe combines it into before cooking, e.g. "spice bowl", "sauce"; the same words for every ingredient in that bowl; null when it is prepped on its own)},
          "steps": array of strings (one cooking action per step, in order, no numbering)
        }
        Allowed ingredient units: g, kg, ml, l, tsp, tbsp, cup, oz, lb, count, or null for unitless items.
        List every tool the recipe needs, once each. Put preparation (chopping, measuring, mixing a marinade) in an ingredient's prep_note and keep steps to the cooking. Give ingredients a prep_bowl only when the source itself combines them before the heat goes on (a spice mix, a sauce whisked together, the aromatics added at once); never invent a bowl.
        SCHEMA;
    }

    /**
     * The prompt section naming the tool words a model may use. Kinds only:
     * the lanes that ask for a specific recipe ignore what the household owns.
     *
     * @param  iterable<string>  $kinds
     */
    public static function kindsSection(iterable $kinds): string
    {
        $list = implode(', ', is_array($kinds) ? $kinds : iterator_to_array($kinds, false));

        return "Kitchen tool words you may use:\n{$list}\n\nPick tool words from this list; only use another word if none fits.";
    }
}
