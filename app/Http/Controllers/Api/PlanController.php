<?php

namespace App\Http\Controllers\Api;

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * DECISIONS.md #7 (pull half) — read-only plan endpoints for external
 * consumers (second brain, calendar apps).
 */
class PlanController extends Controller
{
    /** Floating local start times per slot; each meal blocks one hour. */
    private const SLOT_TIMES = [
        'breakfast' => ['080000', '090000'],
        'lunch' => ['120000', '130000'],
        'dinner' => ['180000', '190000'],
    ];

    /**
     * The most recent locked week as JSON: Mon–Fri days[], each day's slots
     * mapping to the planned recipe (or null).
     */
    public function current(): JsonResponse
    {
        $plan = MealPlan::query()
            ->where('status', MealPlanStatus::Locked)
            ->orderByDesc('week_start_date')
            ->first();

        abort_unless($plan !== null, 404, 'No locked week.');

        $plan->load('plannedMeals.recipe');

        $days = collect(range(0, 4))->map(function (int $offset) use ($plan) {
            $date = $plan->week_start_date->copy()->addDays($offset);

            $slots = collect(MealSlot::cases())->mapWithKeys(function (MealSlot $slot) use ($plan, $date) {
                $meal = $plan->plannedMeals->first(
                    fn (PlannedMeal $meal) => $meal->date->isSameDay($date) && $meal->slot === $slot,
                );

                return [
                    $slot->value => $meal === null ? null : [
                        'recipe_id' => $meal->recipe->id,
                        'title' => $meal->recipe->title,
                        'prep_minutes' => $meal->recipe->prep_minutes,
                        'cook_minutes' => $meal->recipe->cook_minutes,
                    ],
                ];
            });

            return ['date' => $date->toDateString(), 'slots' => $slots];
        });

        return response()->json([
            'week_start_date' => $plan->week_start_date->toDateString(),
            'days' => $days,
        ]);
    }

    /**
     * iCal feed of every planned meal in locked and completed weeks. One
     * VEVENT per meal, UID stable per planned_meal id, floating local slot
     * times (08:00 / 12:00 / 18:00).
     */
    public function ical(): Response
    {
        $meals = PlannedMeal::query()
            ->whereHas('mealPlan', fn ($query) => $query->whereIn(
                'status',
                [MealPlanStatus::Locked, MealPlanStatus::Completed],
            ))
            ->with('recipe')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mealboard//Meal Plan//EN',
            'CALSCALE:GREGORIAN',
        ];

        $stamp = now()->utc()->format('Ymd\THis\Z');

        foreach ($meals as $meal) {
            [$start, $end] = self::SLOT_TIMES[$meal->slot->value];
            $day = $meal->date->format('Ymd');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:planned-meal-{$meal->id}@mealboard";
            $lines[] = "DTSTAMP:{$stamp}";
            $lines[] = "DTSTART:{$day}T{$start}";
            $lines[] = "DTEND:{$day}T{$end}";
            $lines[] = 'SUMMARY:'.$this->escapeIcs(ucfirst($meal->slot->value).': '.$meal->recipe->title);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    private function escapeIcs(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\;', '\,', '\n'],
            $value,
        );
    }
}
