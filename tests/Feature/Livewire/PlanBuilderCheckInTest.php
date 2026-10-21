<?php

use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Livewire\PlanBuilder;
use App\Models\KitchenToolKind;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// The factory's recipes need a skillet; the household owns it, so none is flagged.
beforeEach(fn () => KitchenToolKind::where('name', 'skillet')->update(['owned' => true]));

function checkInScreen(): Testable
{
    return Livewire::actingAs(User::factory()->create())->test(PlanBuilder::class);
}

it('opens the week check-in from Fill the week and from Fill the gaps instead of filling', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create();

    checkInScreen()
        ->assertSee('Fill the week')
        ->call('openCheckIn')
        ->assertSet('checkInOpen', true)
        ->assertSee('Just fill it');

    expect($plan->plannedMeals()->count())->toBe(0);

    $plan->plannedMeals()->create(['recipe_id' => Recipe::first()->id, 'date' => $plan->week_start_date, 'slot' => MealSlot::Dinner]);

    checkInScreen()->assertSee('Fill the gaps')->call('openCheckIn')->assertSet('checkInOpen', true);
});

it('fills without saving answers when the household presses Just fill it', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create();

    checkInScreen()
        ->call('openCheckIn')
        ->call('toggleSkip', $plan->week_start_date->toDateString(), 'lunch')
        ->call('justFill')
        ->assertSet('checkInOpen', false);

    expect($plan->plannedMeals()->count())->toBe(15)
        ->and($plan->fresh()->check_in)->toBeNull();
});

it('saves the check-in answers on the plan, fills around them and reopens pre-filled', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create();
    $friday = $plan->week_start_date->copy()->addDays(4)->toDateString();
    $wednesday = $plan->week_start_date->copy()->addDays(2)->toDateString();

    checkInScreen()
        ->call('openCheckIn')
        ->call('toggleSkip', $wednesday, 'lunch')
        ->call('toggleSkipDay', $friday)
        ->call('submitCheckIn')
        ->assertSet('checkInOpen', false)
        ->assertSee('Out');

    expect($plan->plannedMeals()->count())->toBe(11)
        ->and($plan->fresh()->check_in['skipped'])->toHaveCount(4);

    checkInScreen()
        ->call('openCheckIn')
        ->assertSet('skips', [$wednesday.'|lunch', $friday.'|breakfast', $friday.'|lunch', $friday.'|dinner']);
});

it('toggles a whole day and single slots in the open sheet', function () {
    $monday = MealPlan::factory()->create()->week_start_date->toDateString();

    checkInScreen()
        ->call('openCheckIn')
        ->call('toggleSkipDay', $monday)
        ->assertSet('skips', [$monday.'|breakfast', $monday.'|lunch', $monday.'|dinner'])
        ->call('toggleSkip', $monday, 'lunch')
        ->assertSet('skips', [$monday.'|breakfast', $monday.'|dinner'])
        ->call('toggleSkipDay', $monday)
        ->assertSet('skips', [$monday.'|breakfast', $monday.'|dinner', $monday.'|lunch'])
        ->call('toggleSkipDay', $monday)
        ->assertSet('skips', []);
});

it('shows a skipped slot as Out and removes the skip when a recipe is chosen there', function () {
    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create([
        'week_start_date' => '2099-01-05',
        'check_in' => ['skipped' => [['date' => '2099-01-05', 'slot' => 'lunch']]],
    ]);

    checkInScreen()
        ->assertSee('Out')
        ->call('openPicker', '2099-01-05', 'lunch')
        ->call('choose', $recipe->id);

    expect($plan->fresh()->check_in['skipped'])->toBe([])
        ->and($plan->plannedMeals()->count())->toBe(1);
});

it('keeps the check-in unavailable on a locked week', function () {
    MealPlan::factory()->locked()->create();

    checkInScreen()->call('openCheckIn')->assertStatus(403);
    checkInScreen()->call('justFill')->assertStatus(403);
    checkInScreen()->call('submitCheckIn')->assertStatus(403);
});
