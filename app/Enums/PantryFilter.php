<?php

namespace App\Enums;

/** Which ingredient tiles the pantry staples section shows. */
enum PantryFilter: string
{
    case All = 'all';
    case Staples = 'staples';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Staples => 'On hand',
            self::Others => 'Everything else',
        };
    }
}
