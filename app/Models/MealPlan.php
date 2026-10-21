<?php

namespace App\Models;

use App\Enums\MealPlanStatus;
use Carbon\CarbonInterface;
use Database\Factories\MealPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'reviewed_dates',
        'check_in',
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
            'reviewed_dates' => 'array',
            'check_in' => 'array',
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

    /** The plan whose week starts on the given date (YYYY-MM-DD), if any. */
    public static function forWeek(string $weekStart): ?self
    {
        return static::query()->whereDate('week_start_date', $weekStart)->first();
    }

    /**
     * How many whole weeks this plan is a stale week (GLOSSARY): positive
     * only for a locked week whose dates are past while no newer week is
     * locked; 0 for this week, a week ahead, a draft, a completed week, and
     * an older week a newer locked one has replaced. Weeks start on Monday
     * whatever locale Carbon is set to, so a Sunday evening still counts as
     * the week being shopped.
     */
    public function weeksStale(): int
    {
        if ($this->status !== MealPlanStatus::Locked || $this->newerWeekLocked()) {
            return 0;
        }

        $weekStart = $this->week_start_date->copy()->startOfWeek(CarbonInterface::MONDAY);
        $thisWeek = now()->startOfWeek(CarbonInterface::MONDAY);

        return max(0, (int) round($weekStart->diffInDays($thisWeek) / 7));
    }

    private function newerWeekLocked(): bool
    {
        return static::query()
            ->where('status', MealPlanStatus::Locked)
            ->whereDate('week_start_date', '>', $this->week_start_date->toDateString())
            ->exists();
    }

    /**
     * The week's skipped slots (GLOSSARY) as "YYYY-MM-DD|slot" keys, the same
     * keys the week screen uses for a cell.
     *
     * @return Collection<int, string>
     */
    public function skippedSlots(): Collection
    {
        return collect($this->check_in['skipped'] ?? [])
            ->map(fn (array $skip) => $skip['date'].'|'.$skip['slot'])
            ->values();
    }

    /**
     * Save the skipped slots (as "YYYY-MM-DD|slot" keys), keeping every other
     * check-in answer as it was.
     *
     * @param  iterable<string>  $keys
     */
    public function saveSkippedSlots(iterable $keys): void
    {
        $skipped = collect($keys)
            ->unique()
            ->map(function (string $key) {
                [$date, $slot] = explode('|', $key);

                return ['date' => $date, 'slot' => $slot];
            })
            ->values()
            ->all();

        $this->update(['check_in' => [...($this->check_in ?? []), 'skipped' => $skipped]]);
    }

    /**
     * The week's quick nights (GLOSSARY) as "YYYY-MM-DD" dates.
     *
     * @return Collection<int, string>
     */
    public function quickNights(): Collection
    {
        return collect($this->check_in['quick_nights'] ?? [])->values();
    }

    /**
     * Save the quick nights (as "YYYY-MM-DD" dates), keeping every other
     * check-in answer as it was.
     *
     * @param  iterable<string>  $dates
     */
    public function saveQuickNights(iterable $dates): void
    {
        $this->update(['check_in' => [...($this->check_in ?? []), 'quick_nights' => collect($dates)->unique()->sort()->values()->all()]]);
    }

    /** The cuisine the household craves this week (GLOSSARY), if they chose one. */
    public function craving(): ?string
    {
        return $this->check_in['craving'] ?? null;
    }

    /** Save the craving, keeping every other check-in answer as it was; null clears it. */
    public function saveCraving(?string $cuisine): void
    {
        $checkIn = $this->check_in ?? [];
        unset($checkIn['craving']);

        if ($cuisine !== null && trim($cuisine) !== '') {
            $checkIn['craving'] = trim($cuisine);
        }

        $this->update(['check_in' => $checkIn]);
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
