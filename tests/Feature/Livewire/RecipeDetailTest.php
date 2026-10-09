<?php

use App\Enums\RecipeStatus;
use App\Livewire\RecipeDetail;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

it('renders the detail page with meta, markdown instructions, and ingredient rows', function () {
    $recipe = Recipe::factory()->approved()->unshaped()->create([
        'title' => 'Miso Salmon Bowl',
        'instructions' => "## Steps\n\n1. Marinate the salmon.",
        'cuisine' => 'japanese',
    ]);
    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'salmon fillet'])->id,
        ['qty' => 2, 'unit' => 'count', 'note' => 'skin on'],
    );

    $this->actingAs(User::factory()->create())
        ->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSeeLivewire(RecipeDetail::class)
        ->assertSee('Miso Salmon Bowl')
        ->assertSee('<h2>Steps</h2>', false) // markdown rendered
        ->assertSee('salmon fillet')
        ->assertSee('skin on');
});

it('redirects guests to login', function () {
    $recipe = Recipe::factory()->create();

    $this->get("/recipes/{$recipe->id}")->assertRedirect('/login');
});

it('persists edits to fields and ingredient pivot rows', function () {
    $recipe = Recipe::factory()->approved()->shaped()->create(['title' => 'Old Title']);
    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'old ingredient'])->id,
        ['qty' => 1, 'unit' => 'cup', 'note' => null],
    );

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->set('title', 'New Title')
        ->set('cuisine', 'Mexican')
        ->set('tagsInput', 'quick, Spicy')
        ->set('rows', [
            ['name' => 'Black Beans', 'qty' => '1.5', 'unit' => 'cup', 'prep_note' => 'rinsed'],
            ['name' => 'lime', 'qty' => '1', 'unit' => 'count', 'prep_note' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $recipe->refresh();

    expect($recipe->title)->toBe('New Title')
        ->and($recipe->cuisine)->toBe('mexican')
        ->and($recipe->tags)->toBe(['quick', 'spicy'])
        ->and($recipe->ingredients)->toHaveCount(2);

    $beans = $recipe->ingredients->firstWhere('name', 'black beans');
    expect($beans)->not->toBeNull()
        ->and((float) $beans->pivot->qty)->toBe(1.5)
        ->and($beans->pivot->unit)->toBe('cup')
        ->and($beans->pivot->note)->toBe('rinsed');

    expect($recipe->ingredients->firstWhere('name', 'old ingredient'))->toBeNull();
});

it('rejects a unit outside the normalized set', function () {
    $recipe = Recipe::factory()->approved()->shaped()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->set('rows', [
            ['name' => 'flour', 'qty' => '2', 'unit' => 'handful', 'prep_note' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['rows.0.unit']);

    expect($recipe->fresh()->ingredients->pluck('name')->contains('flour'))->toBeFalse();
});

it('requires a title', function () {
    $recipe = Recipe::factory()->approved()->shaped()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

it('stamps approval attribution when approving', function () {
    $approver = User::factory()->create();
    $recipe = Recipe::factory()->create(['status' => RecipeStatus::Pending]);

    Livewire::actingAs($approver)
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('setStatus', 'approved');

    $recipe->refresh();

    expect($recipe->status)->toBe(RecipeStatus::Approved)
        ->and($recipe->approved_at)->not->toBeNull()
        ->and($recipe->approved_by)->toBe($approver->id);
});

it('can reject and archive a recipe', function (string $status) {
    $recipe = Recipe::factory()->create(['status' => RecipeStatus::Pending]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('setStatus', $status);

    expect($recipe->fresh()->status)->toBe(RecipeStatus::from($status));
})->with(['rejected', 'archived']);

it('edits tools, prep notes and steps of a shaped recipe', function () {
    $recipe = Recipe::factory()->approved()->shaped()->create();

    $form = Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->assertSet('tools', [['alternatives' => 'skillet', 'count' => 1]])
        ->assertCount('steps', 2);

    $form->set('tools', [['alternatives' => 'flat-top griddle, large skillet', 'count' => 2], ['alternatives' => 'tongs', 'count' => 1]])
        ->set('steps', ['Heat the griddle.', 'Sear.'])
        ->set('rows', [['name' => 'scallion', 'qty' => '3', 'unit' => 'count', 'prep_note' => 'sliced thin']])
        ->call('save')
        ->assertHasNoErrors();

    $recipe = $recipe->fresh();
    expect($recipe->recipeTools)->toHaveCount(2)
        ->and($recipe->recipeTools[0]->count)->toBe(2)
        ->and($recipe->recipeTools[0]->alternatives->pluck('word')->all())->toBe(['flat-top griddle', 'large skillet'])
        ->and($recipe->cookingSteps->pluck('text')->all())->toBe(['Heat the griddle.', 'Sear.'])
        ->and($recipe->ingredients->first()->pivot->note)->toBe('sliced thin');
});

it('refuses to save an edit with an empty group', function (string $emptied) {
    $recipe = Recipe::factory()->approved()->shaped()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->set($emptied, [])
        ->call('save')
        ->assertHasErrors([$emptied]);
})->with(['tools', 'rows', 'steps']);

it('shows the old method read-only when editing an unshaped recipe, and saving with the shape works', function () {
    $recipe = Recipe::factory()->approved()->unshaped()->create(['instructions' => '1. Boil the kettle.']);
    $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'tea'])->id, ['qty' => 1, 'unit' => 'count', 'note' => null]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->assertSee('Old method')
        ->assertSee('Boil the kettle.')
        ->assertSet('tools', [])
        ->assertSet('steps', [])
        ->call('save')
        ->assertHasErrors(['tools', 'steps'])
        ->set('tools', [['alternatives' => 'kettle', 'count' => 1]])
        ->set('steps', ['Boil the kettle.', 'Steep the tea.'])
        ->call('save')
        ->assertHasNoErrors();

    $recipe = $recipe->fresh();
    expect($recipe->hasShape())->toBeTrue()
        ->and($recipe->cookingSteps)->toHaveCount(2);
});

it('does not show the old-method panel when editing a shaped recipe', function () {
    $recipe = Recipe::factory()->approved()->shaped()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->assertDontSee('Old method');
});
