<?php

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Livewire\PlanBuilder;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function planBuilder(): Testable
{
    return Livewire::actingAs(User::factory()->create())->test(PlanBuilder::class);
}

it('renders the plan builder for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/plan')
        ->assertOk()
        ->assertSeeLivewire(PlanBuilder::class);
});

it('redirects guests to login', function () {
    $this->get('/plan')->assertRedirect('/login');
});

it('creates next week as a draft plan starting on a Monday', function () {
    planBuilder()->call('createNextWeek');

    $plan = MealPlan::sole();

    expect($plan->status)->toBe(MealPlanStatus::Draft)
        ->and($plan->week_start_date->isMonday())->toBeTrue()
        ->and($plan->week_start_date->isAfter(now()))->toBeTrue();
});

it('skips to the following Monday when next week is already planned', function () {
    planBuilder()->call('createNextWeek')->call('createNextWeek');

    $mondays = MealPlan::query()->orderBy('week_start_date')->pluck('week_start_date');

    expect($mondays)->toHaveCount(2)
        ->and($mondays[1]->toDateString())->toBe($mondays[0]->copy()->addWeek()->toDateString());
});

it('auto-fills the empty slots of the selected week', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create();

    planBuilder()->call('autoFill');

    expect($plan->plannedMeals()->count())->toBe(15);
});

it('swaps a slot recipe via the picker and keeps the slot unique', function () {
    $plan = MealPlan::factory()->create();
    $monday = $plan->week_start_date->toDateString();

    $first = Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner, 'title' => 'First Dinner']);
    $second = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'title' => 'Second Dinner']);

    $component = planBuilder()
        ->call('openPicker', $monday, 'dinner')
        ->call('choose', $first->id);

    expect($plan->plannedMeals()->sole())
        ->recipe_id->toBe($first->id)
        ->slot->toBe(MealSlot::Dinner);

    $component
        ->call('openPicker', $monday, 'dinner')
        ->call('choose', $second->id);

    // Swap replaced the recipe in place — still exactly one row for the slot.
    expect($plan->plannedMeals()->sole())->recipe_id->toBe($second->id);
});

it('only offers approved recipes matching the slot meal type or any in the picker', function () {
    MealPlan::factory()->create();

    Recipe::factory()->approved()->create(['meal_type' => MealType::Dinner, 'title' => 'Dinner Fit']);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'title' => 'Anytime Fit']);
    Recipe::factory()->approved()->create(['meal_type' => MealType::Breakfast, 'title' => 'Breakfast Misfit']);
    Recipe::factory()->create(['meal_type' => MealType::Dinner, 'title' => 'Pending Misfit']);

    planBuilder()
        ->call('openPicker', MealPlan::sole()->week_start_date->toDateString(), 'dinner')
        ->assertSee('Dinner Fit')
        ->assertSee('Anytime Fit')
        ->assertDontSee('Breakfast Misfit')
        ->assertDontSee('Pending Misfit');
});

it('rejects picking an ineligible recipe server-side', function () {
    $plan = MealPlan::factory()->create();
    $breakfastOnly = Recipe::factory()->approved()->create(['meal_type' => MealType::Breakfast]);

    planBuilder()
        ->call('openPicker', $plan->week_start_date->toDateString(), 'dinner')
        ->call('choose', $breakfastOnly->id)
        ->assertStatus(404);

    expect($plan->plannedMeals()->count())->toBe(0);
});

it('clears a slot', function () {
    $plan = MealPlan::factory()->create();
    $monday = $plan->week_start_date->toDateString();

    $plan->plannedMeals()->create([
        'recipe_id' => Recipe::factory()->approved()->create()->id,
        'date' => $monday,
        'slot' => MealSlot::Lunch,
    ]);

    planBuilder()->call('clearSlot', $monday, 'lunch');

    expect($plan->plannedMeals()->count())->toBe(0);
});
