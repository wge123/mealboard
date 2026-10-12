<?php

namespace App\Enums;

/** Which kitchen tool tiles the settings section shows. */
enum KitchenToolFilter: string
{
    case All = 'all';
    case Owned = 'owned';
    case NotOwned = 'not-owned';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Owned => 'Owned',
            self::NotOwned => "Don't have",
        };
    }
}
