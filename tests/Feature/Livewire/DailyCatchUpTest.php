<?php

use App\Livewire\DailyCatchUp;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonInterface;
use Livewire\Livewire;

function catchUpMeal(MealPlan $plan, string $date, string $slot, string $title): PlannedMeal
{
    return $plan->plannedMeals()->create([
        'recipe_id' => Recipe::factory()->approved()->create(['title' => $title])->id,
        'date' => $date,
        'slot' => $slot,
    ]);
}

it('renders for an authenticated user and redirects guests', function () {
    $this->actingAs(User::factory()->create())
        ->get('/log')
        ->assertOk()
        ->assertSeeLivewire(DailyCatchUp::class);

    auth()->logout();

    $this->get('/log')->assertRedirect('/login');
});

it('lists only yesterday\'s unlogged meals for the current user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $plan = MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(2)->toDateString(),
    ]);

    $yesterday = today()->subDay()->toDateString();

    $loggedByMe = catchUpMeal($plan, $yesterday, 'breakfast', 'Logged Porridge');
    $unlogged = catchUpMeal($plan, $yesterday, 'lunch', 'Unlogged Salad');
    $loggedByOther = catchUpMeal($plan, $yesterday, 'dinner', 'Partner Logged Curry');
    catchUpMeal($plan, today()->subDays(2)->toDateString(), 'dinner', 'Older Stew');
    catchUpMeal($plan, today()->toDateString(), 'dinner', 'Tonight Tacos');

    MealLog::factory()->create(['planned_meal_id' => $loggedByMe->id, 'user_id' => $user->id]);
    MealLog::factory()->create(['planned_meal_id' => $loggedByOther->id, 'user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(DailyCatchUp::class)
        ->assertSee('Unlogged Salad')
        // The partner's log must not hide the meal from ME.
        ->assertSee('Partner Logged Curry')
        ->assertDontSee('Logged Porridge')
        ->assertDontSee('Older Stew')
        ->assertDontSee('Tonight Tacos');

    expect($unlogged->logs()->count())->toBe(0);
});

it('excludes yesterday\'s meals on draft plans — logging opens at lock', function () {
    $draft = MealPlan::factory()->create([
        'week_start_date' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(2)->toDateString(),
    ]);

    catchUpMeal($draft, today()->subDay()->toDateString(), 'dinner', 'Draft Dinner');

    Livewire::actingAs(User::factory()->create())
        ->test(DailyCatchUp::class)
        ->assertDontSee('Draft Dinner')
        ->assertSee('All caught up');
});

it('shows the empty state when everything is logged', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(DailyCatchUp::class)
        ->assertSee('All caught up')
        ->assertSee('Nothing left to log from yesterday.');
});
