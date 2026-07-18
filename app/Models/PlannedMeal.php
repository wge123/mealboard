<?php

namespace App\Models;

use App\Enums\MealSlot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlannedMeal extends Model
{
    /** @use HasFactory<\Database\Factories\PlannedMealFactory> */
    use HasFactory;

    protected $fillable = [
        'meal_plan_id',
        'recipe_id',
        'date',
        'slot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'slot' => MealSlot::class,
        ];
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MealLog::class);
    }
}
