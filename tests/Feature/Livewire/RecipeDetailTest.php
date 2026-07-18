<?php

use App\Enums\RecipeStatus;
use App\Livewire\RecipeDetail;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

it('renders the detail page with meta, markdown instructions, and ingredient rows', function () {
    $recipe = Recipe::factory()->approved()->create([
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
    $recipe = Recipe::factory()->approved()->create(['title' => 'Old Title']);
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
            ['name' => 'Black Beans', 'qty' => '1.5', 'unit' => 'cup', 'note' => 'rinsed'],
            ['name' => 'lime', 'qty' => '1', 'unit' => 'count', 'note' => ''],
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
    $recipe = Recipe::factory()->approved()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeDetail::class, ['recipe' => $recipe])
        ->call('startEditing')
        ->set('rows', [
            ['name' => 'flour', 'qty' => '2', 'unit' => 'handful', 'note' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['rows.0.unit']);

    expect($recipe->fresh()->ingredients)->toHaveCount(0);
});

it('requires a title', function () {
    $recipe = Recipe::factory()->approved()->create();

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
