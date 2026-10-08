<?php

namespace App\Models;

use App\Enums\MealPlanStatus;
use Carbon\CarbonInterface;
use Database\Factories\MealPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    /** @use HasFactory<MealPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'week_start_date',
        'status',
        'locked_at',
        'locked_by',
        'checked_items',
        'purchased_at',
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
            'checked_items' => 'array',
            'purchased_at' => 'datetime',
        ];
    }

    /**
     * The newest locked week — the one every surface (MCP tool, list push,
     * Shop tab, plan feed) shops from. Drafts and completed weeks never
     * qualify, so a draft left unlocked loses to an older locked week; read
     * weeksStale() before acting on it.
     */
    public static function latestLocked(): ?self
    {
        return static::query()
            ->where('status', MealPlanStatus::Locked)
            ->orderByDesc('week_start_date')
            ->first();
    }

    /**
     * Whole weeks between this plan's week and the current one: 0 for this
     * week, positive for a past (stale) week, negative for one still ahead.
     * Weeks start on Monday whatever locale Carbon is set to, so a Sunday
     * evening still counts as the week being shopped.
     */
    public function weeksStale(): int
    {
        $weekStart = $this->week_start_date->copy()->startOfWeek(CarbonInterface::MONDAY);
        $thisWeek = now()->startOfWeek(CarbonInterface::MONDAY);

        return (int) round($weekStart->diffInDays($thisWeek) / 7);
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
