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
use App\Models\WalmartMatch;
use Illuminate\Support\Carbon;
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

    shoppingListPage($plan)->call('toggleItem', 'flour|mass');

    expect($plan->refresh()->checked_items)->toBe(['flour|mass']);

    // A brand-new component instance (fresh request) sees the checked state.
    shoppingListPage($plan)->assertSeeHtml('line-through');

    // Toggling again unchecks and persists that too.
    shoppingListPage($plan)->call('toggleItem', 'flour|mass');

    expect($plan->refresh()->checked_items)->toBe([]);

    shoppingListPage($plan)->assertDontSeeHtml('line-through');
});

it('passes the staples switch through to the list', function () {
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $plan = lockedPlanWith([[$salt, 1, 'tsp']]);

    shoppingListPage($plan)
        ->set('includeStaples', true)
        ->assertSee('1 tsp salt');
});

it('exports the lines as a markdown checklist under category headings', function () {
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([[$milk, 250, 'ml'], [$flour, 1.5, 'kg']]);

    expect(shoppingListPage($plan)->instance()->markdownExport())->toBe(
        "## Dairy\n".
        "- [ ] 250 ml milk\n".
        "\n".
        "## Pantry\n".
        '- [ ] 1.5 kg flour',
    );
});

it('exports one label per line for the Instacart handoff', function () {
    $milk = Ingredient::factory()->create(['name' => 'milk', 'category' => IngredientCategory::Dairy]);
    $flour = Ingredient::factory()->create(['name' => 'flour', 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([[$milk, 250, 'ml'], [$flour, 1.5, 'kg']]);

    expect(shoppingListPage($plan)->instance()->plainExport())->toBe(
        "250 ml milk\n1.5 kg flour",
    );
});

it('saves a pasted product URL and flips the row to a direct link', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    $plan = lockedPlanWith([[$onion, 2, 'count']]);

    $component = shoppingListPage($plan)
        ->assertSeeHtml('https://www.walmart.com/search?q=yellow+onion')
        ->set('foundUrls.yellow onion', 'https://www.walmart.com/ip/yellow-onion/44390949')
        ->call('saveMatch', 'yellow onion');

    $match = WalmartMatch::sole();

    expect($match->ingredient_id)->toBe($onion->id)
        ->and($match->product_url)->toBe('https://www.walmart.com/ip/yellow-onion/44390949')
        ->and($match->product_name)->toBe('yellow onion')
        ->and($match->last_confirmed_at)->not->toBeNull();

    // Same request cycle: the row already renders as a direct product link.
    $component
        ->assertSeeHtml('href="https://www.walmart.com/ip/yellow-onion/44390949"')
        ->assertDontSeeHtml('https://www.walmart.com/search?q=yellow+onion');
});

it('rejects a non-URL paste with an inline error and saves nothing', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    $plan = lockedPlanWith([[$onion, 2, 'count']]);

    shoppingListPage($plan)
        ->set('foundUrls.yellow onion', 'not a url')
        ->call('saveMatch', 'yellow onion')
        ->assertHasErrors('foundUrls.yellow onion');

    expect(WalmartMatch::count())->toBe(0);
});

it('still persists checked state with the tappable rows', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    $plan = lockedPlanWith([[$onion, 2, 'count']]);

    shoppingListPage($plan)->call('toggleItem', 'yellow onion|count');

    expect($plan->refresh()->checked_items)->toBe(['yellow onion|count']);

    shoppingListPage($plan)->assertSeeHtml('line-through');
});

/**
 * The argument the browser sends when the page's only `$method` control is
 * clicked: the `$method(...)` argument as the browser reads it, which must be
 * one well-formed JS string literal.
 */
function clickedArgument(Testable $page, string $method): string
{
    preg_match('/wire:click="'.$method.'\((.*?)\)"/', $page->html(), $click);
    $argument = html_entity_decode($click[1], ENT_QUOTES | ENT_HTML5);

    expect($argument)->toMatch('/^\'(?:[^\'\\\\]|\\\\.)*\'$/');

    return json_decode('"'.substr($argument, 1, -1).'"', flags: JSON_THROW_ON_ERROR);
}

it('toggles a line whose ingredient name has an apostrophe', function () {
    $yeast = Ingredient::factory()->create(['name' => "baker's yeast", 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([[$yeast, 7, 'g']]);

    $key = clickedArgument(shoppingListPage($plan), 'toggleItem');

    shoppingListPage($plan)->call('toggleItem', $key)->assertSeeHtml('line-through');
    shoppingListPage($plan)->call('toggleItem', $key)->assertDontSeeHtml('line-through');
});

it('saves a product match for an ingredient whose name has an apostrophe', function () {
    $yeast = Ingredient::factory()->create(['name' => "baker's yeast", 'category' => IngredientCategory::Pantry]);
    $plan = lockedPlanWith([[$yeast, 7, 'g']]);

    $name = clickedArgument(shoppingListPage($plan), 'saveMatch');

    shoppingListPage($plan)
        ->set("foundUrls.{$name}", 'https://www.walmart.com/ip/bakers-yeast/10450997')
        ->call('saveMatch', $name)
        ->assertHasNoErrors();

    expect(WalmartMatch::sole()->ingredient_id)->toBe($yeast->id);
});

it('renders the cart link without a checked line', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);
    $chicken = Ingredient::factory()->create(['name' => 'chicken thighs', 'category' => IngredientCategory::Meat]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $chicken->id,
        'product_url' => 'https://www.walmart.com/ip/chicken-thighs/10315356',
    ]);
    $plan = lockedPlanWith([[$onion, 2, 'count'], [$chicken, 500, 'g']]);

    shoppingListPage($plan)
        ->call('toggleItem', 'chicken thighs|mass')
        ->assertSeeHtml('href="https://affil.walmart.com/cart/addToCart?items=44390949"')
        ->assertSee('Add matched items to Walmart cart');
});

it('warns when the week it shows is stale', function () {
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00')); // five weeks on

    shoppingListPage($plan)
        ->assertSee('Stale week')
        ->assertSee('5 weeks ago');
});

it('shows no stale-week warning for this week', function () {
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-07-26 18:00')); // Sunday, still this week

    shoppingListPage($plan)->assertDontSee('Stale week');
});

it('shows no stale-week warning for an older week once a newer one is locked', function () {
    $older = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    shoppingListPage($older)->assertDontSee('Stale week');
});

it('shows no stale-week warning for a completed week', function () {
    $plan = MealPlan::factory()->create(['week_start_date' => '2026-07-20', 'status' => MealPlanStatus::Completed]);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    shoppingListPage($plan)->assertDontSee('Stale week');
});
