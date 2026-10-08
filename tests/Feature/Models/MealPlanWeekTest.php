<?php

use App\Enums\MealPlanStatus;
use App\Models\MealPlan;
use Illuminate\Support\Carbon;

/*
 * The one place every surface (MCP tool, list push, Shop tab, plan feed)
 * gets its "latest locked week" and "weeks stale" from. Weeks start on
 * Monday whatever the app locale says, so a Sunday evening still belongs to
 * the week that is being shopped.
 */

afterEach(fn () => Carbon::setLocale('en'));

it('answers the latest locked week by week start, ignoring drafts and completed weeks', function () {
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    $latest = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    MealPlan::factory()->create(['week_start_date' => '2026-07-27']); // draft
    MealPlan::factory()->create(['week_start_date' => '2026-08-03', 'status' => MealPlanStatus::Completed]);

    expect(MealPlan::latestLocked()?->id)->toBe($latest->id);
});

it('answers null when no week is locked', function () {
    MealPlan::factory()->create(); // draft only

    expect(MealPlan::latestLocked())->toBeNull();
});

it('is zero weeks stale during its own week', function () {
    $this->travelTo(Carbon::parse('2026-07-22 09:00')); // Wednesday

    expect(mealPlanWeek('2026-07-20')->weeksStale())->toBe(0);
});

it('is zero weeks stale on the Sunday that closes its week', function () {
    $this->travelTo(Carbon::parse('2026-07-26 18:00')); // Sunday

    expect(mealPlanWeek('2026-07-20')->weeksStale())->toBe(0);
});

it('keeps weeks Monday-based on a Sunday under a locale whose weeks start on Sunday', function () {
    Carbon::setLocale('en_US'); // first_day_of_week = Sunday
    $this->travelTo(Carbon::parse('2026-07-26 18:00')); // Sunday

    expect(mealPlanWeek('2026-07-20')->weeksStale())->toBe(0);
});

it('keeps weeks Monday-based under a locale whose weeks start on Saturday', function () {
    Carbon::setLocale('ar'); // first_day_of_week = Saturday
    $this->travelTo(Carbon::parse('2026-07-25 12:00')); // Saturday

    expect(mealPlanWeek('2026-07-20')->weeksStale())->toBe(0);
});

it('counts whole weeks for a past week', function () {
    $this->travelTo(Carbon::parse('2026-08-30 09:00')); // Sunday, five weeks on

    expect(mealPlanWeek('2026-07-20')->weeksStale())->toBe(5);
});

it('turns stale the Monday after its week', function () {
    $this->travelTo(Carbon::parse('2026-07-27 00:10'));

    expect(mealPlanWeek('2026-07-20')->weeksStale())->toBe(1);
});

it('is not stale while its week is still ahead', function () {
    $this->travelTo(Carbon::parse('2026-07-22 09:00'));

    expect(mealPlanWeek('2026-08-03')->weeksStale())->toBe(0);
});

it('is not stale when a newer week has been locked', function () {
    $older = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    expect($older->weeksStale())->toBe(0);
});

it('is not stale once the week is completed', function () {
    $completed = MealPlan::factory()->create(['week_start_date' => '2026-07-20', 'status' => MealPlanStatus::Completed]);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    expect($completed->weeksStale())->toBe(0);
});

it('is not stale while the week is a draft', function () {
    $draft = MealPlan::factory()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    expect($draft->weeksStale())->toBe(0);
});

function mealPlanWeek(string $monday): MealPlan
{
    return MealPlan::factory()->locked()->make(['week_start_date' => $monday]);
}
