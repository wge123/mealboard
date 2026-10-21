<?php

use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Livewire\RecipeCreate;
use App\Models\Recipe;
use App\Models\User;
use App\Support\HouseholdPreferences;
use App\Support\MissingKitchenTools;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

it('renders the create form for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/recipes/create')
        ->assertOk()
        ->assertSeeLivewire(RecipeCreate::class);
});

it('redirects guests to login', function () {
    $this->get('/recipes/create')->assertRedirect('/login');
});

it('parses pasted text into editable ingredient rows', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', "2 cups diced onion\n1/2 tsp salt\neggs")
        ->call('parsePaste')
        ->assertSet('rows', [
            ['name' => 'onion', 'qty' => '2', 'unit' => 'cup', 'prep_note' => 'diced', 'prep_bowl' => ''],
            ['name' => 'salt', 'qty' => '0.5', 'unit' => 'tsp', 'prep_note' => '', 'prep_bowl' => ''],
            ['name' => 'eggs', 'qty' => '', 'unit' => '', 'prep_note' => '', 'prep_bowl' => ''],
        ]);
});

it('saves a manual recipe with ingredient pivot rows and redirects to it', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('title', 'Weeknight Fried Rice')
        ->set('description', 'Uses up leftover rice.')
        ->set('sourceUrl', 'https://example.com/recipes/weeknight-fried-rice')
        ->set('mealType', 'dinner')
        ->set('cuisine', 'chinese')
        ->set('prepMinutes', 10)
        ->set('cookMinutes', 10)
        ->set('servings', 2)
        ->set('tools', [['alternatives' => 'wok, large skillet', 'count' => 1]])
        ->set('steps', ['Fry aromatics.', 'Add rice.'])
        ->set('tagsInput', 'quick, leftovers')
        ->set('paste', "2 cups cooked rice\n2 eggs")
        ->call('parsePaste')
        ->call('save')
        ->assertHasNoErrors();

    $recipe = Recipe::firstWhere('title', 'Weeknight Fried Rice');

    expect($recipe)->not->toBeNull()
        ->and($recipe->source)->toBe(RecipeSource::Manual)
        ->and($recipe->status)->toBe(RecipeStatus::Approved)
        ->and($recipe->tags)->toBe(['quick', 'leftovers'])
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->hasShape())->toBeTrue()
        ->and($recipe->cookingSteps->pluck('text')->all())->toBe(['Fry aromatics.', 'Add rice.'])
        ->and($recipe->recipeTools)->toHaveCount(1)
        ->and($recipe->recipeTools[0]->alternatives->pluck('word')->all())->toBe(['wok', 'large skillet']);

    $rice = $recipe->ingredients->firstWhere('name', 'rice');
    expect($rice)->not->toBeNull()
        ->and((float) $rice->pivot->qty)->toBe(2.0)
        ->and($rice->pivot->unit)->toBe('cup')
        ->and($rice->pivot->note)->toBe('cooked');

    $component->assertRedirect("/recipes/{$recipe->id}");
});

it('AI-parses paste into tools, ingredient rows and steps with an AI badge', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: json_encode([
        'title' => 'Fried Rice',
        'description' => 'Uses up leftover rice.',
        'meal_type' => 'dinner',
        'prep_minutes' => 5,
        'cook_minutes' => 10,
        'servings' => 2,
        'cuisine' => null,
        'tags' => [],
        'source_url' => 'https://example.com/pasted-recipe',
        'tools' => [['alternatives' => ['wok', 'large skillet'], 'count' => 1], ['alternatives' => ['spatula'], 'count' => 2]],
        'ingredients' => [
            ['qty' => 2, 'unit' => 'cup', 'name' => 'cooked rice', 'prep_note' => null],
            ['qty' => 3, 'unit' => null, 'name' => 'garlic', 'prep_note' => 'minced'],
        ],
        'steps' => ['Fry the garlic.', 'Add the rice.'],
    ]))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', 'Some messy pasted recipe blog text')
        ->call('aiParse')
        ->assertSet('rows', [
            ['name' => 'cooked rice', 'qty' => '2', 'unit' => 'cup', 'prep_note' => '', 'prep_bowl' => ''],
            ['name' => 'garlic', 'qty' => '3', 'unit' => '', 'prep_note' => 'minced', 'prep_bowl' => ''],
        ])
        ->assertSet('tools', [
            ['alternatives' => 'wok, large skillet', 'count' => 1],
            ['alternatives' => 'spatula', 'count' => 2],
        ])
        ->assertSet('steps', ['Fry the garlic.', 'Add the rice.'])
        ->assertSet('parsedWith', 'ai')
        ->assertSet('parseError', '')
        ->assertSet('paste', '')
        ->assertSee('AI parsed');
});

it('AI-parses a realistic pasted recipe (ingredients and method together) into all three groups', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: json_encode([
        'tools' => [['alternatives' => ['saucepan'], 'count' => 1]],
        'ingredients' => [
            ['qty' => 2, 'unit' => 'cup', 'name' => 'rice', 'prep_note' => 'rinsed'],
            ['qty' => 1, 'unit' => 'tsp', 'name' => 'salt', 'prep_note' => null],
        ],
        'steps' => ['Boil the rice.', 'Season.'],
    ]))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', "Simple Rice\n\nIngredients\n2 cups rice, rinsed\n1 tsp salt\n\nMethod\nBoil the rice in a saucepan. Season.")
        ->call('aiParse')
        ->assertSet('rows', [
            ['name' => 'rice', 'qty' => '2', 'unit' => 'cup', 'prep_note' => 'rinsed', 'prep_bowl' => ''],
            ['name' => 'salt', 'qty' => '1', 'unit' => 'tsp', 'prep_note' => '', 'prep_bowl' => ''],
        ])
        ->assertSet('tools', [['alternatives' => 'saucepan', 'count' => 1]])
        ->assertSet('steps', ['Boil the rice.', 'Season.'])
        ->assertSet('title', '')
        ->assertSet('sourceUrl', '');

    Process::assertRan(fn ($process) => str_contains($process->input ?? '', '2 cups rice, rinsed')
        && str_contains($process->input ?? '', 'Boil the rice in a saucepan'));
});

it('shows the failed shape check on the form and fills nothing from a heuristic', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: json_encode(['tools' => [], 'ingredients' => [['name' => 'eggs']], 'steps' => []]))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', 'eggs')
        ->call('aiParse')
        ->assertSet('rows', [['name' => '', 'qty' => '', 'unit' => '', 'prep_note' => '', 'prep_bowl' => '']])
        ->assertSet('parsedWith', '')
        ->assertSee('tools: at least one tool is required')
        ->assertSee('steps: at least one step is required');
});

it('shows a claude CLI failure as the form error and fills nothing', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: '', errorOutput: 'model overloaded', exitCode: 1)]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('steps', ['Keep me.'])
        ->set('paste', "2 cups diced onion\n1/2 tsp salt")
        ->call('aiParse')
        ->assertSet('rows', [['name' => '', 'qty' => '', 'unit' => '', 'prep_note' => '', 'prep_bowl' => '']])
        ->assertSet('steps', ['Keep me.'])
        ->assertSet('parsedWith', '')
        ->assertSee('model overloaded');
});

it('lets a bug in the AI parse path surface instead of masking it', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => fn () => throw new LogicException('bug')]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', 'eggs')
        ->call('aiParse');
})->throws(LogicException::class);

it('requires a title and validates units against the normalized set', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('rows', [
            ['name' => 'flour', 'qty' => '1', 'unit' => 'handful', 'prep_note' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['title' => 'required', 'sourceUrl' => 'required', 'rows.0.unit']);

    expect(Recipe::count())->toBe(0);
});

it('refuses a form with an empty tools, ingredients or steps group', function (string $emptied) {
    $form = Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('title', 'Toast')
        ->set('description', 'Bread, heated.')
        ->set('sourceUrl', 'https://example.com/toast')
        ->set('mealType', 'breakfast')
        ->set('prepMinutes', 1)
        ->set('cookMinutes', 2)
        ->set('servings', 1)
        ->set('tools', [['alternatives' => 'toaster', 'count' => 1]])
        ->set('rows', [['name' => 'bread', 'qty' => '2', 'unit' => '', 'prep_note' => 'sliced']])
        ->set('steps', ['Toast the bread.'])
        ->set($emptied, [])
        ->call('save')
        ->assertHasErrors([$emptied]);

    expect(Recipe::count())->toBe(0);
})->with(['tools', 'rows', 'steps']);

it('uses the glossary word kitchen tool on the tool rows', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->assertSee('Kitchen tool alternatives')
        ->assertSee('Remove kitchen tool')
        ->assertSee('+ Add kitchen tool')
        ->assertDontSee('Add tool');
});

it('puts the paste card before the titled section cards and shows the slow AI parse as busy', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->assertSeeInOrder(['Paste a recipe', 'Details', 'Kitchen tools', 'Ingredients', 'Cooking steps'])
        ->assertSee('Parsing', false)
        ->assertSee('wire:target="aiParse"', false);
});

it('shows a row error right under the field it belongs to', function () {
    $html = Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('rows', [['name' => 'flour', 'qty' => '1', 'unit' => 'handful', 'prep_note' => '']])
        ->call('save')
        ->html();

    expect($html)->toMatch('/aria-label="Unit".*?<\/select>(\s|<!--.*?-->)*<p[^>]*text-error[^>]*>[^<]*unit/s');
});

it('shows the empty ingredients error on the form', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('rows', [])
        ->call('save')
        ->assertHasErrors(['rows'])
        ->assertSee('The rows field is required');
});

it('treats a blank tool or step as an empty group', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('title', 'Toast')
        ->set('tools', [['alternatives' => '', 'count' => 1]])
        ->set('steps', [''])
        ->call('save')
        ->assertHasErrors(['tools', 'steps']);
});

it('starts servings at the household size on the create form', function () {
    app(HouseholdPreferences::class)->setHouseholdSize(4);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->assertSet('servings', 4);
});

function pastinaPageResponse()
{
    return Http::response(file_get_contents(base_path('tests/Fixtures/recipe-pages/loveandlemons-pastina-soup.html')));
}

it('fills the form from a pasted recipe link and saves it approved, imported, with the page address as source', function () {
    Process::fake();
    Http::fake(['www.loveandlemons.com/*' => pastinaPageResponse()]);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->assertHasNoErrors()
        ->assertSet('title', 'Pastina Soup')
        ->assertSet('sourceUrl', 'https://www.loveandlemons.com/pastina-soup/')
        ->assertSet('prepMinutes', 20)
        ->assertSet('cookMinutes', 45)
        ->assertSet('servings', 6)
        ->assertSet('importError', '');

    expect($component->get('rows'))->toHaveCount(14)
        ->and($component->get('rows')[0])->toBe(['name' => 'extra-virgin olive oil', 'qty' => '2', 'unit' => 'tbsp', 'prep_note' => '', 'prep_bowl' => ''])
        ->and($component->get('steps'))->toHaveCount(5)
        ->and(collect($component->get('tools'))->pluck('alternatives')->all())->toContain('pot, dutch oven', 'blender');

    $component->call('save')->assertHasNoErrors();

    $recipe = Recipe::firstWhere('title', 'Pastina Soup');

    expect($recipe->source)->toBe(RecipeSource::Imported)
        ->and($recipe->status)->toBe(RecipeStatus::Approved)
        ->and($recipe->source_url)->toBe('https://www.loveandlemons.com/pastina-soup/')
        ->and($recipe->approved_by)->toBe($user->id)
        ->and($recipe->ingredients)->toHaveCount(14)
        ->and($recipe->cookingSteps)->toHaveCount(5);

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mealboard'));
    Process::assertNothingRan();
});

it('shows a missing kitchen tool on an imported recipe the usual way', function () {
    Http::fake(['www.loveandlemons.com/*' => pastinaPageResponse()]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->call('save');

    $recipe = Recipe::firstWhere('title', 'Pastina Soup');

    expect(collect(app(MissingKitchenTools::class)->for($recipe))->map->label()->all())->toContain('blender');
});

it('says the page could not be fetched and fills nothing', function () {
    Http::fake(['*' => Http::response('Forbidden', 403)]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->assertSet('title', '')
        ->assertSee('could not be fetched');
});

it('says a page with no recipe data has none and fills nothing', function () {
    Http::fake(['*' => Http::response('<html><body>An article</body></html>')]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://example.com/article')
        ->call('importLink')
        ->assertSet('title', '')
        ->assertSee('no recipe data');
});

it('says could not fetch for a 403, a challenge page and a timeout, and offers paste-the-text with the address filled', function (Closure $fake) {
    Http::fake(['*' => $fake]);
    $address = 'https://www.foodnetwork.com/recipes/some-recipe';

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', $address)
        ->call('importLink')
        ->assertSet('title', '')
        ->assertSee('could not be fetched')
        ->assertDontSee('no recipe data')
        ->assertSee('paste the recipe text')
        ->assertSet('sourceUrl', $address);
})->with([
    'a 403' => [fn () => Http::response('Access Denied', 403)],
    'a challenge page' => [fn () => Http::response('<html><title>Just a moment...</title></html>', 403, ['cf-mitigated' => 'challenge'])],
    'a challenge page served as 200' => [fn () => Http::response('<html><head><title>Just a moment...</title></head><body>Checking your browser</body></html>', 200, ['cf-mitigated' => 'challenge'])],
    'a timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out')],
]);

it('says no recipe data and offers paste-the-text with the address filled', function () {
    Http::fake(['*' => Http::response('<html><body>An article</body></html>')]);
    $address = 'https://example.com/article';

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', $address)
        ->call('importLink')
        ->assertSee('no recipe data')
        ->assertDontSee('could not be fetched')
        ->assertSee('paste the recipe text')
        ->assertSet('sourceUrl', $address);
});

it('opens the library recipe instead of importing when the pasted address is already in the library', function () {
    Http::fake();
    Process::fake();
    $existing = Recipe::factory()->create(['source_url' => 'https://www.loveandlemons.com/pastina-soup/']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->assertRedirect("/recipes/{$existing->id}")
        ->assertSet('title', '');

    Http::assertNothingSent();
    expect(Recipe::count())->toBe(1);
});

it('matches a pasted address already in the library ignoring a trailing slash', function () {
    Http::fake();
    $existing = Recipe::factory()->create(['source_url' => 'https://www.loveandlemons.com/pastina-soup']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->assertRedirect("/recipes/{$existing->id}");

    Http::assertNothingSent();
});

it('warns about a title matching a library recipe and still saves', function () {
    Http::fake(['www.loveandlemons.com/*' => pastinaPageResponse()]);
    Recipe::factory()->create(['title' => 'pastina soup', 'source_url' => 'https://other.example.com/pastina']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->assertSee('already have a recipe called')
        ->call('save')
        ->assertHasNoErrors();

    expect(Recipe::where('title', 'Pastina Soup')->count())->toBe(1)
        ->and(Recipe::count())->toBe(2);
});

it('shows no title warning when no library recipe has the title', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('title', 'Something New')
        ->assertDontSee('already have a recipe called');
});

it('warns naming an avoided ingredient on an import and still saves it', function () {
    Http::fake(['www.loveandlemons.com/*' => pastinaPageResponse()]);
    app(HouseholdPreferences::class)->avoid('parmesan');

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->assertSee('Contains an avoided ingredient: parmesan')
        ->call('save')
        ->assertHasNoErrors();

    expect(Recipe::firstWhere('title', 'Pastina Soup')->status)->toBe(RecipeStatus::Approved);
});

it('applies no weekday limit to an import: 60 minutes and 15 ingredients save without a limit message', function () {
    Http::fake(['www.loveandlemons.com/*' => pastinaPageResponse()]);
    $limits = app(HouseholdPreferences::class)->weekdayLimits();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://www.loveandlemons.com/pastina-soup/')
        ->call('importLink')
        ->set('prepMinutes', 20)
        ->set('cookMinutes', 40)
        ->call('addRow')
        ->set('rows.14.name', 'extra thing')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDontSee('weekday limit');

    $recipe = Recipe::firstWhere('title', 'Pastina Soup');
    expect($recipe)->not->toBeNull()
        ->and($recipe->prep_minutes + $recipe->cook_minutes)->toBe(60)
        ->and($recipe->ingredients)->toHaveCount(15)
        ->and($limits['minutes'])->toBeLessThan(60);
});

it('leaves times the page does not state blank and required for the household to fill in', function () {
    Http::fake(['*' => Http::response('<html><head><script type="application/ld+json">{"@type":"Recipe","name":"Plain Toast","description":"Toast.","recipeIngredient":["2 slices bread"],"recipeInstructions":[{"@type":"HowToStep","text":"Toast the bread in a toaster."}]}</script></head></html>')]);
    Process::fake();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('link', 'https://example.com/plain-toast')
        ->call('importLink')
        ->assertSet('prepMinutes', null)
        ->assertSet('cookMinutes', null)
        ->call('save')
        ->assertHasErrors(['prepMinutes', 'cookMinutes']);

    Process::assertNothingRan();
});
