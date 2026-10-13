<?php

namespace App\Models;

use Database\Factories\MealLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealLog extends Model
{
    /** @use HasFactory<MealLogFactory> */
    use HasFactory;

    protected $fillable = [
        'planned_meal_id',
        'user_id',
        'ate_it',
        'rating',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ate_it' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function plannedMeal(): BelongsTo
    {
        return $this->belongsTo(PlannedMeal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
