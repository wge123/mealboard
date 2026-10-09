<?php

use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Livewire\RecipeCreate;
use App\Models\Recipe;
use App\Models\User;
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
            ['name' => 'onion', 'qty' => '2', 'unit' => 'cup', 'prep_note' => 'diced'],
            ['name' => 'salt', 'qty' => '0.5', 'unit' => 'tsp', 'prep_note' => ''],
            ['name' => 'eggs', 'qty' => '', 'unit' => '', 'prep_note' => ''],
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
        ->and($recipe->status)->toBe(RecipeStatus::Pending)
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
            ['name' => 'cooked rice', 'qty' => '2', 'unit' => 'cup', 'prep_note' => ''],
            ['name' => 'garlic', 'qty' => '3', 'unit' => '', 'prep_note' => 'minced'],
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

it('falls back with a visible error when the shaping pass fails its check', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: json_encode(['title' => 'x']))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', 'eggs')
        ->call('aiParse')
        ->assertSet('parsedWith', 'fallback')
        ->assertSee('AI parse failed');
});

it('falls back to the heuristic parser with a visible error when the CLI fails', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: '', errorOutput: 'model overloaded', exitCode: 1)]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('steps', ['Keep me.'])
        ->set('paste', "2 cups diced onion\n1/2 tsp salt")
        ->call('aiParse')
        ->assertSet('rows', [
            ['name' => 'onion', 'qty' => '2', 'unit' => 'cup', 'prep_note' => 'diced'],
            ['name' => 'salt', 'qty' => '0.5', 'unit' => 'tsp', 'prep_note' => ''],
        ])
        ->assertSet('steps', ['Keep me.'])
        ->assertSet('parsedWith', 'fallback')
        ->assertSee('Heuristic parsed (AI unavailable)')
        ->assertSee('AI parse failed');
});

it('falls back with a visible error when the CLI returns malformed JSON', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: 'Sorry, I cannot help with that.')]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', 'eggs')
        ->call('aiParse')
        ->assertSet('rows', [
            ['name' => 'eggs', 'qty' => '', 'unit' => '', 'prep_note' => ''],
        ])
        ->assertSet('parsedWith', 'fallback')
        ->assertSee('Heuristic parsed (AI unavailable)');
});

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

it('treats a blank tool or step as an empty group', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('title', 'Toast')
        ->set('tools', [['alternatives' => '', 'count' => 1]])
        ->set('steps', [''])
        ->call('save')
        ->assertHasErrors(['tools', 'steps']);
});
