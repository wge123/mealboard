<?php

namespace App\Enums;

enum RecipeSource: string
{
    case Manual = 'manual';
    case Discovered = 'discovered';
    case Imported = 'imported';
}
