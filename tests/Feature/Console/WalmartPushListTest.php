<?php

use App\Enums\IngredientCategory;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Walmart\ListBrowser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/*
 * The browser is an in-test stand-in that records what the command adds —
 * tests must never launch Chrome (docs/tier3-list.md: the live supervised run
 * verifies the real browser). The buy list's own rules are tested once at the
 * Shopping list module; these tests only check the push walks it.
 */

/**
 * Give the plan one planned meal whose recipe uses the named ingredients
 * (count units unless overridden).
 *
 * @param  list<array{0: string, 1?: string|null}>  $ingredients  [name, unit]
 */
function pushPlanMeal(MealPlan $plan, array $ingredients, MealSlot $slot = MealSlot::Dinner): void
{
    $recipe = Recipe::factory()->unshaped()->approved()->create(['meal_type' => MealType::Any]);

    foreach ($ingredients as $line) {
        $ingredient = Ingredient::query()->where('name', $line[0])->first()
            ?? Ingredient::factory()->create(['name' => $line[0], 'category' => IngredientCategory::Produce]);

        $recipe->ingredients()->attach($ingredient->id, ['qty' => 1, 'unit' => $line[1] ?? 'count']);
    }

    $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => $plan->week_start_date->toDateString(),
        'slot' => $slot,
    ]);
}

/** Bind a stand-in browser and return it; `added` lists every addItem call in order. */
function fakeListBrowser(): object
{
    $browser = new class implements ListBrowser
    {
        /** @var list<string> */
        public array $added = [];

        public function open(string $listUrl): void {}

        public function addItem(string $keywords): void
        {
            $this->added[] = $keywords;
        }

        public function close(): void {}
    };

    app()->instance(ListBrowser::class, $browser);

    return $browser;
}

function configurePush(): void
{
    config(['mealboard.chrome_profile' => '/tmp/mealboard-chrome', 'mealboard.walmart_list_url' => 'https://www.walmart.com/lists/example']);
}

it('refuses to start when MEALBOARD_CHROME_PROFILE is unset', function () {
    config(['mealboard.chrome_profile' => null, 'mealboard.walmart_list_url' => 'https://www.walmart.com/lists/example']);

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'MEALBOARD_CHROME_PROFILE is not set');
});

it('refuses to start when MEALBOARD_WALMART_LIST_URL is unset', function () {
    config(['mealboard.chrome_profile' => '/tmp/mealboard-chrome', 'mealboard.walmart_list_url' => null]);

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'MEALBOARD_WALMART_LIST_URL is not set');
});

it('pushes two ingredients whose keywords clean to the same words as two entries', function () {
    configurePush();
    $browser = fakeListBrowser();
    $this->travelTo(Carbon::parse('2026-07-22 09:00'));

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushPlanMeal($plan, [['diced yellow onion'], ['yellow onion']]);

    expect(Artisan::call('walmart:push-list'))->toBe(0)
        ->and($browser->added)->toBe(['yellow onion', 'yellow onion']);
});

it('pushes an ingredient with two lines once', function () {
    configurePush();
    $browser = fakeListBrowser();
    $this->travelTo(Carbon::parse('2026-07-22 09:00'));

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushPlanMeal($plan, [['chicken', 'g']]);
    pushPlanMeal($plan, [['chicken', 'cup']], MealSlot::Lunch);

    expect(Artisan::call('walmart:push-list'))->toBe(0)
        ->and($browser->added)->toBe(['chicken']);
});

it('pushes nothing for checked or pantry staple ingredients', function () {
    configurePush();
    $browser = fakeListBrowser();
    $this->travelTo(Carbon::parse('2026-07-22 09:00'));

    Ingredient::factory()->pantryStaple()->create(['name' => 'salt']);
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushPlanMeal($plan, [['carrots'], ['yellow onion'], ['salt']]);
    $plan->update(['checked_items' => ['yellow onion|count']]);

    expect(Artisan::call('walmart:push-list'))->toBe(0)
        ->and($browser->added)->toBe(['carrots']);
});

it('exits non-zero naming the stale week before any browser work', function () {
    configurePush();
    $this->mock(ListBrowser::class)->shouldNotReceive('open');

    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'The latest locked week (2026-07-20) is 5 weeks stale');
});

it('still pushes a stale week when it is named explicitly', function () {
    configurePush();
    $browser = fakeListBrowser();

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushPlanMeal($plan, [['carrots']]);
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    expect(Artisan::call('walmart:push-list', ['--week' => '2026-07-20']))->toBe(0)
        ->and($browser->added)->toBe(['carrots']);
});

it('skips an ingredient whose keywords clean to nothing and names it', function () {
    configurePush();
    $browser = fakeListBrowser();
    $this->travelTo(Carbon::parse('2026-07-22 09:00'));

    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    pushPlanMeal($plan, [['carrots'], [', to taste']]);

    expect(Artisan::call('walmart:push-list'))->toBe(0)
        ->and($browser->added)->toBe(['carrots'])
        ->and(Artisan::output())->toContain('Skipped ", to taste": its search keywords are empty');
});

it('exits non-zero when no week is locked', function () {
    configurePush();
    $this->mock(ListBrowser::class)->shouldNotReceive('open');

    MealPlan::factory()->create(['week_start_date' => '2026-07-20']); // draft only

    expect(fn () => Artisan::call('walmart:push-list'))
        ->toThrow(RuntimeException::class, 'No locked week.');
});

it('exits non-zero when the named week has no meal plan', function () {
    configurePush();
    $this->mock(ListBrowser::class)->shouldNotReceive('open');

    expect(fn () => Artisan::call('walmart:push-list', ['--week' => '2026-07-20']))
        ->toThrow(RuntimeException::class, 'No meal plan for week starting 2026-07-20.');
});

it('refuses a named draft week before any browser work', function () {
    configurePush();
    $this->mock(ListBrowser::class)->shouldNotReceive('open');

    $plan = MealPlan::factory()->create(['week_start_date' => '2026-07-20']); // draft
    pushPlanMeal($plan, [['carrots']]);

    expect(fn () => Artisan::call('walmart:push-list', ['--week' => '2026-07-20']))
        ->toThrow(RuntimeException::class, 'The week starting 2026-07-20 is a draft — lock it before pushing its list.');
});
