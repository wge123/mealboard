<?php

namespace App\Support;

use App\Enums\MealSlot;
use Carbon\CarbonInterface;

/**
 * The "YYYY-MM-DD|slot" key that names one cell of the week: a meal's place,
 * a skipped slot, a cell on the week screen. The one owner of how it is
 * built and split.
 */
final class SlotKey
{
    public static function make(CarbonInterface|string $date, MealSlot|string $slot): string
    {
        return ($date instanceof CarbonInterface ? $date->toDateString() : $date)
            .'|'.($slot instanceof MealSlot ? $slot->value : $slot);
    }

    /**
     * @return array{0: string, 1: string} the date and the slot's value, unchecked
     */
    public static function parse(string $key): array
    {
        [$date, $slot] = explode('|', $key, 2);

        return [$date, $slot];
    }

    /** Sort position: by date, then in meal order through the day. */
    public static function order(string $key): string
    {
        [$date, $slot] = self::parse($key);

        return $date.'|'.array_search($slot, array_column(MealSlot::cases(), 'value'), true);
    }
}
