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
    shoppingListPage($plan)->assertSeeHtml('line-through');

    // Toggling again unchecks and persists that too.
    shoppingListPage($plan)->call('toggleItem', 'flour|g');

    expect($plan->refresh()->checked_items)->toBe([]);

    shoppingListPage($plan)->assertDontSeeHtml('line-through');
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

it('links a matched row straight to its Walmart product', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);
    $plan = lockedPlanWith([[$onion, 2, 'count']]);

    shoppingListPage($plan)
        ->assertSeeHtml('href="https://www.walmart.com/ip/yellow-onion/44390949"')
        ->assertSeeHtml('target="_blank"')
        ->assertDontSeeHtml('https://www.walmart.com/search')
        ->assertSee('↗ product');
});

it('links an unmatched row to a Walmart search on cleaned keywords', function () {
    // "frozen" is a prep word — cleaning drops it from the search query.
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);
    $plan = lockedPlanWith([[$peas, 500, 'g']]);

    shoppingListPage($plan)
        ->assertSeeHtml('href="https://www.walmart.com/search?q=peas"')
        ->assertSeeHtml('target="_blank"')
        ->assertSee('↗ search');
});

it('url-encodes multi-word search keywords', function () {
    $chicken = Ingredient::factory()->create(['name' => 'chicken thighs', 'category' => IngredientCategory::Meat]);
    $plan = lockedPlanWith([[$chicken, 1, 'lb']]);

    shoppingListPage($plan)
        ->assertSeeHtml('href="https://www.walmart.com/search?q=chicken+thighs"');
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

it('keeps Walmart links working when staples are toggled in', function () {
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    WalmartMatch::factory()->create([
        'ingredient_id' => $salt->id,
        'product_url' => 'https://www.walmart.com/ip/salt/10315356',
    ]);
    $chicken = Ingredient::factory()->create(['name' => 'chicken thighs', 'category' => IngredientCategory::Meat]);
    $plan = lockedPlanWith([[$salt, 1, 'tsp'], [$chicken, 500, 'g']]);

    shoppingListPage($plan)
        ->assertDontSeeHtml('https://www.walmart.com/ip/salt/10315356')
        ->assertSeeHtml('href="https://www.walmart.com/search?q=chicken+thighs"')
        ->set('includeStaples', true)
        ->assertSeeHtml('href="https://www.walmart.com/ip/salt/10315356"')
        ->assertSeeHtml('href="https://www.walmart.com/search?q=chicken+thighs"');
});

it('still persists checked state with the tappable rows', function () {
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    $plan = lockedPlanWith([[$onion, 2, 'count']]);

    shoppingListPage($plan)->call('toggleItem', 'yellow onion|count');

    expect($plan->refresh()->checked_items)->toBe(['yellow onion|count']);

    shoppingListPage($plan)->assertSeeHtml('line-through');
});

it('renders one affiliate add-to-cart link covering every matched row', function () {
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
    // Unmatched: contributes a search chip, never an item id.
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);
    $plan = lockedPlanWith([[$onion, 2, 'count'], [$chicken, 500, 'g'], [$peas, 500, 'g']]);

    shoppingListPage($plan)
        ->assertSeeHtml('href="https://affil.walmart.com/cart/addToCart?items=44390949,10315356"')
        ->assertSee('Add matched items to Walmart cart')
        ->assertSeeHtml('href="https://www.walmart.com/search?q=peas"');
});

it('renders no cart link at all when nothing is matched', function () {
    $peas = Ingredient::factory()->create(['name' => 'frozen peas', 'category' => IngredientCategory::Frozen]);
    $plan = lockedPlanWith([[$peas, 500, 'g']]);

    shoppingListPage($plan)
        ->assertDontSeeHtml('affil.walmart.com')
        ->assertDontSee('Add matched items to Walmart cart');
});

it('follows the staples toggle into the cart link', function () {
    $salt = Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    WalmartMatch::factory()->create([
        'ingredient_id' => $salt->id,
        'product_url' => 'https://www.walmart.com/ip/salt/10315356',
    ]);
    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);
    $plan = lockedPlanWith([[$salt, 1, 'tsp'], [$onion, 2, 'count']]);

    shoppingListPage($plan)
        ->assertSeeHtml('href="https://affil.walmart.com/cart/addToCart?items=44390949"')
        ->set('includeStaples', true)
        ->assertSeeHtml('href="https://affil.walmart.com/cart/addToCart?items=44390949,10315356"');
});
