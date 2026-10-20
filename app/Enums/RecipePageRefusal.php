<?php

namespace App\Enums;

enum RecipePageRefusal: string
{
    case CouldNotFetch = 'could-not-fetch';
    case NoRecipeData = 'no-recipe-data';
    case NoIngredients = 'no-ingredients';
    case NoSteps = 'no-steps';

    public function message(): string
    {
        return match ($this) {
            self::CouldNotFetch => 'That page could not be fetched. The site may have blocked the app or the address may be wrong.',
            self::NoRecipeData => 'That page has no recipe data the app can read.',
            self::NoIngredients => 'That page has recipe data but lists no ingredients.',
            self::NoSteps => 'That page has recipe data but lists no steps.',
        };
    }
}
