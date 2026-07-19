<?php

use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Livewire\PlanBuilder;
use App\Models\MealLog;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
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

it('locks the selected draft week, stamping who locked it', function () {
    $user = User::factory()->create();
    $plan = MealPlan::factory()->create();

    Livewire::actingAs($user)->test(PlanBuilder::class)->call('lock');

    $plan->refresh();

    expect($plan->status)->toBe(MealPlanStatus::Locked)
        ->and($plan->locked_at)->not->toBeNull()
        ->and($plan->locked_by)->toBe($user->id);
});

it('rejects mutations on a locked plan server-side', function (string $method, array $args) {
    $plan = MealPlan::factory()->locked()->create();
    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);

    $meal = $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => MealSlot::Dinner,
    ]);

    $component = planBuilder();

    // choose() needs picker state, which openPicker refuses to set on a
    // locked plan — drive it directly to prove the mutation itself is gated.
    if ($method === 'choose') {
        $component->set('pickerDate', $plan->week_start_date->toDateString())
            ->set('pickerSlot', 'dinner');
    }

    $args = array_map(
        fn ($arg) => $arg === ':monday:' ? $plan->week_start_date->toDateString() : ($arg === ':recipe:' ? $recipe->id : $arg),
        $args,
    );

    $component->call($method, ...$args)->assertStatus(403);

    expect($meal->fresh())->not->toBeNull()
        ->and($plan->plannedMeals()->count())->toBe(1);
})->with([
    'autoFill' => ['autoFill', []],
    'openPicker' => ['openPicker', [':monday:', 'lunch']],
    'choose' => ['choose', [':recipe:']],
    'clearSlot' => ['clearSlot', [':monday:', 'dinner']],
]);

it('hides editing controls and shows a locked badge on a locked plan', function () {
    MealPlan::factory()->locked()->create();

    planBuilder()
        ->assertSee('Locked')
        ->assertDontSee('Auto-fill')
        ->assertDontSee('Lock week')
        ->assertDontSee('Swap')
        ->assertDontSee('Clear');
});

it('marks a locked week as completed once the week has ended', function () {
    $plan = MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek()->subWeeks(2)->toDateString(),
    ]);

    planBuilder()->call('markCompleted');

    expect($plan->refresh()->status)->toBe(MealPlanStatus::Completed);
});

it('refuses to complete a locked week that has not ended yet', function () {
    $plan = MealPlan::factory()->locked()->create([
        'week_start_date' => now()->startOfWeek()->addWeek()->toDateString(),
    ]);

    planBuilder()->call('markCompleted')->assertStatus(403);

    expect($plan->refresh()->status)->toBe(MealPlanStatus::Locked);
});

it('refuses to complete a draft week', function () {
    $plan = MealPlan::factory()->create([
        'week_start_date' => now()->startOfWeek()->subWeeks(2)->toDateString(),
    ]);

    planBuilder()->call('markCompleted')->assertStatus(403);

    expect($plan->refresh()->status)->toBe(MealPlanStatus::Draft);
});

it('keeps a draft plan fully editable after another week is locked', function () {
    $locked = MealPlan::factory()->locked()->create();
    $draft = MealPlan::factory()->create([
        'week_start_date' => $locked->week_start_date->copy()->addWeeks(600)->toDateString(),
    ]);

    Recipe::factory()->approved()->count(3)->create(['meal_type' => MealType::Any]);

    planBuilder()
        ->set('planId', $draft->id)
        ->call('autoFill');

    expect($draft->plannedMeals()->count())->toBe(15)
        ->and($locked->plannedMeals()->count())->toBe(0);
});

it('surfaces the breakfast-skip note on a draft plan when the pattern exists', function () {
    MealPlan::factory()->create();

    // 2 of 3 logged breakfast opportunities skipped.
    foreach ([false, false, true] as $ate) {
        MealLog::factory()->create([
            'planned_meal_id' => PlannedMeal::factory()->create(['slot' => MealSlot::Breakfast])->id,
            'ate_it' => $ate,
            'rating' => null,
        ]);
    }

    planBuilder()->assertSee('Breakfasts often skipped — picking faster ones.');
});

it('omits the breakfast-skip note without skip data', function () {
    MealPlan::factory()->create();

    planBuilder()->assertDontSee('Breakfasts often skipped');
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
