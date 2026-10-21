<?php

use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Enums\RecipeStatus;
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

it('marks quick nights in the sheet, shows the limit, and reopens pre-filled', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any, 'prep_minutes' => 5, 'cook_minutes' => 5]);
    $plan = MealPlan::factory()->create();
    $monday = $plan->week_start_date->toDateString();
    $tuesday = $plan->week_start_date->copy()->addDay()->toDateString();
    $friday = $plan->week_start_date->copy()->addDays(4)->toDateString();

    checkInScreen()
        ->call('openCheckIn')
        ->assertSee('Quick nights')
        ->assertSee('20 min')
        ->call('toggleQuickNight', $tuesday)
        ->call('toggleQuickNight', $friday)
        ->call('toggleQuickNight', $friday)
        ->assertSet('quickNights', [$tuesday])
        ->call('toggleSkip', $monday, 'lunch')
        ->call('submitCheckIn');

    expect($plan->fresh()->check_in)->toBe([
        'skipped' => [['date' => $monday, 'slot' => 'lunch']],
        'quick_nights' => [$tuesday],
    ]);

    checkInScreen()->call('openCheckIn')->assertSet('quickNights', [$tuesday]);
});

it('keeps the saved quick nights when the household presses Just fill it', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any, 'prep_minutes' => 5, 'cook_minutes' => 5]);
    $plan = MealPlan::factory()->create(['check_in' => ['quick_nights' => ['2099-01-06']], 'week_start_date' => '2099-01-05']);

    checkInScreen()
        ->call('openCheckIn')
        ->call('toggleQuickNight', '2099-01-07')
        ->call('justFill');

    expect($plan->fresh()->check_in)->toBe(['quick_nights' => ['2099-01-06']]);
});

it('keeps the reason a quick-night dinner stayed empty in the week notice after the fill', function () {
    Recipe::factory()->approved()->count(5)->create(['meal_type' => MealType::Any, 'prep_minutes' => 40, 'cook_minutes' => 20]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05']);

    checkInScreen()
        ->call('openCheckIn')
        ->call('toggleQuickNight', '2099-01-06')
        ->call('submitCheckIn')
        ->assertSee('No recipe under 20 min for Tue dinner');

    expect($plan->plannedMeals()->whereDate('date', '2099-01-06')->where('slot', 'dinner')->exists())->toBeFalse()
        ->and($plan->plannedMeals()->count())->toBe(14);
});

it('refuses a quick night outside the week', function () {
    MealPlan::factory()->create(['week_start_date' => '2099-01-05']);

    checkInScreen()->call('openCheckIn')->call('toggleQuickNight', '2099-02-01')->assertStatus(422);
});

it('lists only the cuisines in the approved library, saves the craving, and reopens pre-filled', function () {
    Recipe::factory()->approved()->create(['cuisine' => 'thai']);
    Recipe::factory()->approved()->create(['cuisine' => 'thai']);
    Recipe::factory()->approved()->create(['cuisine' => 'Italian']);
    Recipe::factory()->approved()->create(['cuisine' => null]);
    Recipe::factory()->create(['status' => RecipeStatus::Pending, 'cuisine' => 'korean']);
    $plan = MealPlan::factory()->create(['check_in' => ['quick_nights' => ['2099-01-06']], 'week_start_date' => '2099-01-05']);

    checkInScreen()
        ->call('openCheckIn')
        ->assertSee('Craving')
        ->assertViewHas('cuisines', ['Italian', 'thai'])
        ->set('craving', 'thai')
        ->call('submitCheckIn');

    expect($plan->fresh()->check_in)->toBe(['quick_nights' => ['2099-01-06'], 'skipped' => [], 'craving' => 'thai']);

    checkInScreen()->call('openCheckIn')->assertSet('craving', 'thai');
});

it('clears the saved craving when none is chosen', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any, 'cuisine' => 'thai']);
    $plan = MealPlan::factory()->create(['check_in' => ['craving' => 'thai']]);

    checkInScreen()->call('openCheckIn')->set('craving', '')->call('submitCheckIn');

    expect($plan->fresh()->check_in)->not->toHaveKey('craving');
});

it('keeps the saved craving when the household presses Just fill it', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any, 'cuisine' => 'thai']);
    $plan = MealPlan::factory()->create(['check_in' => ['craving' => 'thai']]);

    checkInScreen()->call('openCheckIn')->set('craving', '')->call('justFill');

    expect($plan->fresh()->check_in)->toBe(['craving' => 'thai']);
});

it('does not count Out slots as empty gaps or as plannable meals', function () {
    Recipe::factory()->approved()->count(15)->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create();
    $monday = $plan->week_start_date->toDateString();
    $plan->saveSkippedSlots(["{$monday}|lunch", "{$monday}|dinner"]);

    checkInScreen()
        ->call('justFill')
        ->assertSee('13 of 13 meals')
        ->assertDontSee('still empty')
        ->assertDontSee('Fill the gaps');
});

it('saves nothing when the craving is refused', function () {
    Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'cuisine' => 'thai']);
    $plan = MealPlan::factory()->create();

    checkInScreen()
        ->call('openCheckIn')
        ->call('toggleSkip', $plan->week_start_date->toDateString(), 'lunch')
        ->call('toggleQuickNight', $plan->week_start_date->toDateString())
        ->set('craving', 'klingon')
        ->call('submitCheckIn')
        ->assertStatus(422);

    expect($plan->fresh()->check_in)->toBeNull();
});
