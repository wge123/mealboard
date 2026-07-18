<?php

namespace App\Enums;

enum RecipeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
