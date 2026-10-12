<?php

namespace App\Enums;

/** What the recipe library's shelves are grouped by. */
enum LibraryShelving: string
{
    case Meal = 'meal';
    case Cuisine = 'cuisine';
    case Time = 'time';
    case Rating = 'rating';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
