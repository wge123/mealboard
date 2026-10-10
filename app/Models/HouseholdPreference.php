<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The one row of household preferences (weekday limits and household size).
 * Read and written through App\Support\HouseholdPreferences.
 */
class HouseholdPreference extends Model
{
    protected $fillable = ['weekday_minutes_limit', 'weekday_ingredient_limit', 'household_size'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday_minutes_limit' => 'integer',
            'weekday_ingredient_limit' => 'integer',
            'household_size' => 'integer',
        ];
    }
}
