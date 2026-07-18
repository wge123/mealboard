<?php

namespace App\Models;

use App\Enums\MealPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    /** @use HasFactory<\Database\Factories\MealPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'week_start_date',
        'status',
        'locked_at',
        'locked_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'status' => MealPlanStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function plannedMeals(): HasMany
    {
        return $this->hasMany(PlannedMeal::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
