<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One word the household never wants in a meal, stored trimmed and lowercased.
 * Read and written through App\Support\HouseholdPreferences.
 */
class AvoidedIngredient extends Model
{
    protected $fillable = ['word'];
}
