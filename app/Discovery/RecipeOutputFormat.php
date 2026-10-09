<?php

namespace App\Discovery;

/**
 * The one definition of the JSON a model is asked to produce for a recipe in
 * the recipe shape (tools with alternatives and count, ingredients each with
 * a prep_note, and steps). Every model prompt embeds schema(); the matching
 * check is CandidateValidator::check.
 */
final class RecipeOutputFormat
{
    /**
     * The object spec, ready to drop into a prompt. $sourceUrl describes the
     * source_url value, which differs per path (a video extraction injects
     * it itself).
     */
    public static function schema(string $sourceUrl = 'string (see source rule below)'): string
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
          "ingredients": array of {"qty": number or null, "unit": string or null, "name": string, "prep_note": string or null (how it is prepared before cooking, e.g. "diced")},
          "steps": array of strings (one cooking action per step, in order, no numbering)
        }
        Allowed ingredient units: g, kg, ml, l, tsp, tbsp, cup, oz, lb, count, or null for unitless items.
        List every tool the recipe needs, once each. Put preparation (chopping, measuring, mixing a marinade) in an ingredient's prep_note and keep steps to the cooking.
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
