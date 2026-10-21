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

beforeEach(fn () => KitchenToolKind::where('name', 'skillet')->update(['owned' => true]));

function clashScreen(): Testable
{
    return Livewire::actingAs(User::factory()->create())->test(PlanBuilder::class);
}

function planMeal(Recipe $recipe, MealPlan $plan, string $date, string $slot): void
{
    $plan->plannedMeals()->create(['recipe_id' => $recipe->id, 'date' => $date, 'slot' => MealSlot::from($slot)]);
}

it('leaves every planned meal in place when new answers are saved on a filled week', function () {
    Recipe::factory()->approved()->count(20)->create(['meal_type' => MealType::Any, 'prep_minutes' => 40, 'cook_minutes' => 30]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05']);
    clashScreen()->call('autoFill');
    $before = $plan->plannedMeals()->orderBy('id')->get(['id', 'recipe_id', 'date', 'slot'])->toArray();

    clashScreen()
        ->call('openCheckIn')
        ->call('toggleQuickNight', '2099-01-08')
        ->call('toggleSkip', '2099-01-06', 'lunch')
        ->call('submitCheckIn');

    expect($plan->plannedMeals()->orderBy('id')->get(['id', 'recipe_id', 'date', 'slot'])->toArray())->toBe($before);
});

it('flags a dinner over the quick-night limit on a quick night and names the day and limit', function () {
    $slow = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 40, 'cook_minutes' => 30]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05', 'check_in' => ['quick_nights' => ['2099-01-08']]]);
    planMeal($slow, $plan, '2099-01-08', 'dinner');
    planMeal($slow, $plan, '2099-01-09', 'dinner');

    clashScreen()
        ->assertSee('Thu dinner is over your quick-night limit (20 min)')
        ->assertDontSee('Fri dinner is over');
});

it('does not flag a slow lunch on a quick night or a fitting dinner', function () {
    $slow = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 40, 'cook_minutes' => 30]);
    $quick = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 5, 'cook_minutes' => 10]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05', 'check_in' => ['quick_nights' => ['2099-01-08']]]);
    planMeal($slow, $plan, '2099-01-08', 'lunch');
    planMeal($quick, $plan, '2099-01-08', 'dinner');

    clashScreen()->assertDontSee('over your quick-night limit');
});

it('flags a 0-minute dinner on a quick night because its time is unknown', function () {
    $unknown = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 0, 'cook_minutes' => 0]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05', 'check_in' => ['quick_nights' => ['2099-01-08']]]);
    planMeal($unknown, $plan, '2099-01-08', 'dinner');

    clashScreen()->assertSee('Thu dinner is over your quick-night limit');
});

it('flags a meal in a slot skipped after it was filled', function () {
    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05']);
    planMeal($recipe, $plan, '2099-01-07', 'lunch');

    // The sheet lets a filled slot be ticked as skipped, and saving it keeps the meal.
    clashScreen()
        ->call('openCheckIn')
        ->call('toggleSkip', '2099-01-07', 'lunch')
        ->call('submitCheckIn')
        ->assertSee('Wed lunch is marked Out');

    expect($plan->plannedMeals()->whereDate('date', '2099-01-07')->where('slot', 'lunch')->exists())->toBeTrue();
});

it('removes a flag when the meal is replaced with a fitting one or cleared', function () {
    $slow = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 40, 'cook_minutes' => 30]);
    $quick = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 5, 'cook_minutes' => 10]);
    $plan = MealPlan::factory()->create(['week_start_date' => '2099-01-05', 'check_in' => [
        'quick_nights' => ['2099-01-08'],
        'skipped' => [['date' => '2099-01-07', 'slot' => 'lunch']],
    ]]);
    planMeal($slow, $plan, '2099-01-08', 'dinner');
    planMeal($slow, $plan, '2099-01-07', 'lunch');

    clashScreen()
        ->assertSee('Thu dinner is over your quick-night limit')
        ->assertSee('Wed lunch is marked Out')
        ->call('openPicker', '2099-01-08', 'dinner')
        ->call('choose', $quick->id)
        ->assertDontSee('Thu dinner is over your quick-night limit')
        ->call('clearSlot', '2099-01-07', 'lunch')
        ->assertDontSee('Wed lunch is marked Out');
});

it('shows no flags on a locked week', function () {
    $slow = Recipe::factory()->approved()->create(['meal_type' => MealType::Any, 'prep_minutes' => 40, 'cook_minutes' => 30]);
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2099-01-05', 'check_in' => ['quick_nights' => ['2099-01-08']]]);
    planMeal($slow, $plan, '2099-01-08', 'dinner');

    clashScreen()->assertDontSee('over your quick-night limit');
});
