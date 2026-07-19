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
            ['name' => 'onion', 'qty' => '2', 'unit' => 'cup', 'note' => 'diced'],
            ['name' => 'salt', 'qty' => '0.5', 'unit' => 'tsp', 'note' => ''],
            ['name' => 'eggs', 'qty' => '', 'unit' => '', 'note' => ''],
        ]);
});

it('saves a manual recipe with ingredient pivot rows and redirects to it', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('title', 'Weeknight Fried Rice')
        ->set('description', 'Uses up leftover rice.')
        ->set('mealType', 'dinner')
        ->set('cuisine', 'chinese')
        ->set('prepMinutes', 10)
        ->set('cookMinutes', 10)
        ->set('servings', 2)
        ->set('instructions', "1. Fry aromatics.\n2. Add rice.")
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
        ->and($recipe->ingredients)->toHaveCount(2);

    $rice = $recipe->ingredients->firstWhere('name', 'rice');
    expect($rice)->not->toBeNull()
        ->and((float) $rice->pivot->qty)->toBe(2.0)
        ->and($rice->pivot->unit)->toBe('cup')
        ->and($rice->pivot->note)->toBe('cooked');

    $component->assertRedirect("/recipes/{$recipe->id}");
});

it('AI-parses paste into rows and instructions with an AI badge', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: json_encode([
        'ingredients' => [
            ['qty' => 2, 'unit' => 'cups', 'name' => 'Cooked Rice', 'note' => null],
            ['qty' => 3, 'unit' => 'cloves', 'name' => 'garlic', 'note' => 'minced'],
            ['qty' => null, 'unit' => null, 'name' => 'salt', 'note' => null],
        ],
        'instructions' => "1. Fry aromatics.\n2. Add rice.",
    ]))]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('paste', 'Some messy pasted recipe blog text')
        ->call('aiParse')
        ->assertSet('rows', [
            ['name' => 'cooked rice', 'qty' => '2', 'unit' => 'cup', 'note' => ''],
            ['name' => 'garlic', 'qty' => '3', 'unit' => '', 'note' => 'cloves, minced'],
            ['name' => 'salt', 'qty' => '', 'unit' => '', 'note' => ''],
        ])
        ->assertSet('instructions', "1. Fry aromatics.\n2. Add rice.")
        ->assertSet('parsedWith', 'ai')
        ->assertSet('parseError', '')
        ->assertSet('paste', '')
        ->assertSee('AI parsed');
});

it('falls back to the heuristic parser with a visible error when the CLI fails', function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
    Process::fake(['*claude*' => Process::result(output: '', errorOutput: 'model overloaded', exitCode: 1)]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('instructions', 'Keep me.')
        ->set('paste', "2 cups diced onion\n1/2 tsp salt")
        ->call('aiParse')
        ->assertSet('rows', [
            ['name' => 'onion', 'qty' => '2', 'unit' => 'cup', 'note' => 'diced'],
            ['name' => 'salt', 'qty' => '0.5', 'unit' => 'tsp', 'note' => ''],
        ])
        ->assertSet('instructions', 'Keep me.')
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
            ['name' => 'eggs', 'qty' => '', 'unit' => '', 'note' => ''],
        ])
        ->assertSet('parsedWith', 'fallback')
        ->assertSee('Heuristic parsed (AI unavailable)');
});

it('requires a title and validates units against the normalized set', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeCreate::class)
        ->set('rows', [
            ['name' => 'flour', 'qty' => '1', 'unit' => 'handful', 'note' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['title' => 'required', 'rows.0.unit']);

    expect(Recipe::count())->toBe(0);
});
