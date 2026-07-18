<?php

namespace App\Enums;

enum MealPlanStatus: string
{
    case Draft = 'draft';
    case Locked = 'locked';
    case Completed = 'completed';
}
