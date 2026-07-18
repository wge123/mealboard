<?php

namespace App\Enums;

enum IngredientCategory: string
{
    case Produce = 'produce';
    case Meat = 'meat';
    case Dairy = 'dairy';
    case Pantry = 'pantry';
    case Frozen = 'frozen';
    case Bakery = 'bakery';
    case Other = 'other';
}
