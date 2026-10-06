<?php

use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Livewire\ReviewMatches;
use App\Livewire\ShoppingList;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Models\WalmartMatch;
use App\Models\WalmartMatchProposal;
use App\Models\WalmartRejectedItem;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function reviewPage(): Testable
{
    return Livewire::actingAs(User::factory()->create())->test(ReviewMatches::class);
}

function proposalFor(string $name, string $url): WalmartMatchProposal
{
    return WalmartMatchProposal::factory()->create([
        'ingredient_id' => Ingredient::factory()->create(['name' => $name])->id,
        'product_url' => $url,
        'product_name' => ucfirst($name).' product',
    ]);
}

it('renders for an authenticated user and redirects guests', function () {
    proposalFor('feta', 'https://www.walmart.com/ip/feta/10315355');

    $this->get('/walmart/review')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/walmart/review')
        ->assertOk()
        ->assertSeeLivewire(ReviewMatches::class)
        ->assertSee('feta')
        ->assertSee('Feta product');
});

it('confirms an untouched row, rejects a ticked one, and saves a pasted url', function () {
    proposalFor('flour', 'https://www.walmart.com/ip/gv-flour/10403017');
    $tick = proposalFor('chicken breast', 'https://www.walmart.com/ip/tray/14296616416');
    $paste = proposalFor('cabbage', 'https://www.walmart.com/ip/organic-cabbage/5430271509');

    reviewPage()
        ->set("replace.{$tick->id}", true)
        ->set("foundUrls.{$paste->id}", 'https://www.walmart.com/ip/green-cabbage/44390951')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('summary', '1 confirmed, 1 replaced with your URL, 1 rejected.');

    $matches = WalmartMatch::with('ingredient')->get()->mapWithKeys(fn ($m) => [$m->ingredient->name => $m->product_url]);
    $rejected = WalmartRejectedItem::with('ingredient')->get()->map(fn ($r) => $r->ingredient->name.':'.$r->item_id)->sort()->values();

    expect(WalmartMatchProposal::count())->toBe(0)
        ->and($matches->all())->toEqual([
            'flour' => 'https://www.walmart.com/ip/gv-flour/10403017',
            'cabbage' => 'https://www.walmart.com/ip/green-cabbage/44390951',
        ])
        ->and($rejected->all())->toBe(['cabbage:5430271509', 'chicken breast:14296616416']);
});

it('writes nothing when one pasted url carries no item id', function () {
    proposalFor('flour', 'https://www.walmart.com/ip/gv-flour/10403017');
    $paste = proposalFor('cabbage', 'https://www.walmart.com/ip/organic-cabbage/5430271509');

    reviewPage()
        ->set("foundUrls.{$paste->id}", 'https://www.walmart.com/search?q=cabbage')
        ->call('submit')
        ->assertHasErrors("foundUrls.{$paste->id}");

    expect(WalmartMatchProposal::count())->toBe(2)
        ->and(WalmartMatch::count())->toBe(0)
        ->and(WalmartRejectedItem::count())->toBe(0);
});

it('does not reject the proposed id when the pasted url is the same product', function () {
    $paste = proposalFor('cabbage', 'https://www.walmart.com/ip/organic-cabbage/5430271509');

    reviewPage()
        ->set("foundUrls.{$paste->id}", 'https://www.walmart.com/ip/new-slug/5430271509')
        ->call('submit');

    expect(WalmartRejectedItem::count())->toBe(0)
        ->and(WalmartMatch::sole()->product_url)->toBe('https://www.walmart.com/ip/new-slug/5430271509');
});

it('lists low-confidence rows first', function () {
    WalmartMatchProposal::factory()->create(['ingredient_id' => Ingredient::factory()->create(['name' => 'apples'])->id, 'confidence' => 'high']);
    WalmartMatchProposal::factory()->create(['ingredient_id' => Ingredient::factory()->create(['name' => 'yuzu ponzu'])->id, 'confidence' => 'low']);

    reviewPage()->assertSeeInOrder(['yuzu ponzu', 'apples']);
});

it('keeps proposals off the shopping list cart link and points at the review screen', function () {
    $plan = MealPlan::factory()->locked()->create();
    $recipe = Recipe::factory()->approved()->create(['meal_type' => MealType::Any]);
    $flour = Ingredient::factory()->create(['name' => 'flour']);
    $recipe->ingredients()->attach($flour->id, ['qty' => 500, 'unit' => 'g']);
    $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => MealSlot::Dinner,
    ]);
    WalmartMatchProposal::factory()->create([
        'ingredient_id' => $flour->id,
        'product_url' => 'https://www.walmart.com/ip/gv-flour/10403017',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ShoppingList::class, ['mealPlan' => $plan])
        ->assertSee('1 proposed Walmart match waiting for review')
        ->assertSee(route('walmart.review'))
        ->assertDontSee('Add matched items to Walmart cart')
        ->assertDontSee('10403017');
});
