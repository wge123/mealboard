<?php

use App\Enums\MealSlot;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;

beforeEach(function () {
    config()->set('mealboard.api_token', 'test-api-token');
});

it('rejects requests without a token', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    'current' => '/api/plan/current',
    'ical' => '/api/plan/ical',
]);

it('rejects a wrong token', function (string $uri) {
    $this->getJson($uri.'?token=wrong')->assertUnauthorized();

    $this->getJson($uri, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
})->with([
    'current' => '/api/plan/current',
    'ical' => '/api/plan/ical',
]);

it('accepts the token as a Bearer header and as a query parameter', function () {
    MealPlan::factory()->locked()->create();

    $this->getJson('/api/plan/current', ['Authorization' => 'Bearer test-api-token'])->assertOk();
    $this->getJson('/api/plan/current?token=test-api-token')->assertOk();
});

it('returns the most recent locked week with the exact day/slot shape', function () {
    // Older locked week — must NOT be the one returned.
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-06-01']);

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);

    $salmon = Recipe::factory()->create(['title' => 'Salmon Bowls', 'prep_minutes' => 10, 'cook_minutes' => 15]);

    PlannedMeal::factory()->create([
        'meal_plan_id' => $plan->id, 'recipe_id' => $salmon->id,
        'date' => '2026-07-21', 'slot' => MealSlot::Dinner,
    ]);

    $emptySlots = ['breakfast' => null, 'lunch' => null, 'dinner' => null];

    $this->getJson('/api/plan/current?token=test-api-token')
        ->assertOk()
        ->assertExactJson([
            'week_start_date' => '2026-07-20',
            'days' => [
                ['date' => '2026-07-20', 'slots' => $emptySlots],
                ['date' => '2026-07-21', 'slots' => [
                    'breakfast' => null,
                    'lunch' => null,
                    'dinner' => [
                        'recipe_id' => $salmon->id,
                        'title' => 'Salmon Bowls',
                        'prep_minutes' => 10,
                        'cook_minutes' => 15,
                    ],
                ]],
                ['date' => '2026-07-22', 'slots' => $emptySlots],
                ['date' => '2026-07-23', 'slots' => $emptySlots],
                ['date' => '2026-07-24', 'slots' => $emptySlots],
            ],
        ]);
});

it('returns 404 from /api/plan/current when no week is locked', function () {
    MealPlan::factory()->create(); // draft only

    $this->getJson('/api/plan/current?token=test-api-token')->assertNotFound();
});

it('serves an iCal feed of locked and completed weeks with stable per-meal UIDs', function () {
    $locked = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $completed = MealPlan::factory()->create(['week_start_date' => '2026-07-13', 'status' => 'completed']);
    $draft = MealPlan::factory()->create(['week_start_date' => '2026-07-27']);

    $breakfast = PlannedMeal::factory()->create([
        'meal_plan_id' => $locked->id, 'date' => '2026-07-20', 'slot' => MealSlot::Breakfast,
        'recipe_id' => Recipe::factory()->create(['title' => 'Overnight Oats'])->id,
    ]);
    $dinner = PlannedMeal::factory()->create([
        'meal_plan_id' => $completed->id, 'date' => '2026-07-15', 'slot' => MealSlot::Dinner,
        'recipe_id' => Recipe::factory()->create(['title' => 'Salmon Bowls'])->id,
    ]);
    PlannedMeal::factory()->create([
        'meal_plan_id' => $draft->id, 'date' => '2026-07-27', 'slot' => MealSlot::Lunch,
    ]);

    $response = $this->get('/api/plan/ical?token=test-api-token')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $ics = $response->getContent();

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\nVERSION:2.0")
        ->and($ics)->toContain('END:VCALENDAR')
        ->and(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2) // draft excluded
        ->and($ics)->toContain("UID:planned-meal-{$breakfast->id}@mealboard")
        ->and($ics)->toContain("UID:planned-meal-{$dinner->id}@mealboard")
        ->and($ics)->toContain("DTSTART:20260720T080000\r\nDTEND:20260720T090000")
        ->and($ics)->toContain("DTSTART:20260715T180000\r\nDTEND:20260715T190000")
        ->and($ics)->toContain('SUMMARY:Breakfast: Overnight Oats')
        ->and($ics)->toContain('SUMMARY:Dinner: Salmon Bowls');

    // Stable UIDs: a second request yields the identical event identifiers.
    $again = $this->get('/api/plan/ical?token=test-api-token')->getContent();

    preg_match_all('/^UID:.+$/m', $ics, $first);
    preg_match_all('/^UID:.+$/m', $again, $second);

    expect($second[0])->toBe($first[0]);
});
