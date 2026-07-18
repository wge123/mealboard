<?php

use App\Enums\IngredientCategory;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Livewire\ShoppingList;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function shoppingListPage(MealPlan $plan): Testable
{
    return Livewire::actingAs(User::factory()->create())
        ->test(ShoppingList::class, ['mealPlan' => $plan]);
}

/**
 * A locked plan whose single Monday dinner uses one recipe with the given
 * ingredient lines.
 *
 * @param  list<array{0: Ingredient, 1: float|null, 2: string|null}>  $lines
 */
function lockedPlanWith(array $lines): MealPlan
{
    $plan = MealPlan::factory()->locked()->create();
    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);

    foreach ($lines as [$ingredient, $qty, $unit]) {
        $recipe->ingredients()->attach($ingredient->id, ['qty' => $qty, 'unit' => $unit]);
    }

    $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => MealSlot::Dinner,
    ]);

    return $plan;
}

it('renders the shopping list for a locked plan', function () {
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([[$flour, 500, 'g']]);

    $this->actingAs(User::factory()->create())
        ->get("/plan/{$plan->id}/shopping-list")
        ->assertOk()
        ->assertSeeLivewire(ShoppingList::class)
        ->assertSee('flour');
});

it('redirects guests to login', function () {
    $plan = MealPlan::factory()->locked()->create();

    $this->get("/plan/{$plan->id}/shopping-list")->assertRedirect('/login');
});

it('rejects a non-locked plan with a 403', function () {
    $plan = MealPlan::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/plan/{$plan->id}/shopping-list")
        ->assertForbidden();
});

it('renders for a completed plan', function () {
    $plan = MealPlan::factory()->locked()->create(['status' => MealPlanStatus::Completed]);

    $this->actingAs(User::factory()->create())
        ->get("/plan/{$plan->id}/shopping-list")
        ->assertOk();
});

it('persists checked items across component reloads', function () {
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([[$flour, 500, 'g']]);

    shoppingListPage($plan)->call('toggleItem', 'flour|g');

    expect($plan->refresh()->checked_items)->toBe(['flour|g']);

    // A brand-new component instance (fresh request) sees the checked state.
    shoppingListPage($plan)->assertSeeHtml('text-decoration-line-through');

    // Toggling again unchecks and persists that too.
    shoppingListPage($plan)->call('toggleItem', 'flour|g');

    expect($plan->refresh()->checked_items)->toBe([]);

    shoppingListPage($plan)->assertDontSeeHtml('text-decoration-line-through');
});

it('toggles pantry staples into and out of the list', function () {
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $chicken = Ingredient::factory()->create(['name' => 'chicken', 'category' => IngredientCategory::Meat]);
    $plan = lockedPlanWith([[$salt, 1, 'tsp'], [$chicken, 500, 'g']]);

    shoppingListPage($plan)
        ->assertSee('chicken')
        ->assertDontSee('salt')
        ->set('includeStaples', true)
        ->assertSee('salt')
        ->set('includeStaples', false)
        ->assertDontSee('salt');
});

it('produces the exact markdown export', function () {
    $carrot = Ingredient::factory()->create(['name' => 'carrots', 'category' => IngredientCategory::Produce]);
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([
        [$carrot, 3, 'count'],
        [$milk, 250, 'ml'],
        [$flour, 1.5, 'kg'],
    ]);

    expect(shoppingListPage($plan)->instance()->markdownExport())->toBe(
        "## Produce\n".
        "- [ ] 3 carrots\n".
        "\n".
        "## Dairy\n".
        "- [ ] 250 ml milk\n".
        "\n".
        "## Pantry\n".
        '- [ ] 1.5 kg flour',
    );
});

it('produces the exact plain export for Instacart handoff', function () {
    $carrot = Ingredient::factory()->create(['name' => 'carrots', 'category' => IngredientCategory::Produce]);
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([
        [$carrot, 3, 'count'],
        [$milk, 250, 'ml'],
        [$flour, 1.5, 'kg'],
    ]);

    expect(shoppingListPage($plan)->instance()->plainExport())->toBe(
        "3 carrots\n250 ml milk\n1.5 kg flour",
    );
});

it('reflects the staples toggle in the exports', function () {
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $chicken = Ingredient::factory()->create(['name' => 'chicken', 'category' => IngredientCategory::Meat]);
    $plan = lockedPlanWith([[$salt, 1, 'tsp'], [$chicken, 500, 'g']]);

    $component = shoppingListPage($plan);

    expect($component->instance()->plainExport())->toBe('500 g chicken');

    $component->set('includeStaples', true);

    expect($component->instance()->plainExport())->toBe("500 g chicken\n1 tsp salt");
});
