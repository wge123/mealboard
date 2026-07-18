<?php

namespace App\Enums;

enum VideoClassification: string
{
    case LikelyRecipe = 'likely_recipe';
    case NotRecipe = 'not_recipe';
}
