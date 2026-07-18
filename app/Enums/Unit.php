<?php

namespace App\Enums;

enum Unit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
    case Teaspoon = 'tsp';
    case Tablespoon = 'tbsp';
    case Cup = 'cup';
    case Ounce = 'oz';
    case Pound = 'lb';
    case Count = 'count';
}
