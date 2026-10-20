<?php

use App\Enums\RecipeStatus;
use App\Livewire\RecipeApproval;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

/** Three pending recipes, oldest first. */
function deckOfThree(): array
{
    return [
        Recipe::factory()->create(['title' => 'First Card', 'created_at' => now()->subDays(3)]),
        Recipe::factory()->create(['title' => 'Second Card', 'created_at' => now()->subDays(2)]),
        Recipe::factory()->create(['title' => 'Third Card', 'created_at' => now()->subDay()]),
    ];
}

it('shows how many recipes are left to decide', function () {
    deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('3 to go');
});

it('skip sends the top card to the back of the deck and leaves it pending', function () {
    [$first] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('skip', $first->id)
        ->assertSee('Second Card')
        ->assertDontSee('First Card')
        ->assertSee('3 to go');

    expect($first->fresh()->status)->toBe(RecipeStatus::Pending);
});

it('brings a skipped card round again once the rest are decided', function () {
    [$first, $second, $third] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('skip', $first->id)
        ->call('approve', $second->id)
        ->call('reject', $third->id)
        ->assertSee('First Card')
        ->assertSee('1 to go');
});

it('keeps the only card on top when it is skipped', function () {
    $only = Recipe::factory()->create(['title' => 'Lonely Card']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('skip', $only->id)
        ->assertSee('Lonely Card');
});

it('undo after approving puts the recipe back on top as pending, without the approval stamps', function () {
    [$first] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('approve', $first->id)
        ->assertDontSee('First Card')
        ->call('undo')
        ->assertSee('First Card')
        ->assertSee('3 to go');

    $first->refresh();

    expect($first->status)->toBe(RecipeStatus::Pending)
        ->and($first->approved_at)->toBeNull()
        ->and($first->approved_by)->toBeNull();
});

it('undo after rejecting puts the recipe back on top as pending', function () {
    [$first] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('reject', $first->id)
        ->call('undo')
        ->assertSee('First Card');

    expect($first->fresh()->status)->toBe(RecipeStatus::Pending);
});

it('undo after skipping puts the card back on top', function () {
    [$first] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('skip', $first->id)
        ->call('undo')
        ->assertSee('First Card');
});

it('undo walks back several steps, newest first', function () {
    [$first, $second] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('approve', $first->id)
        ->call('skip', $second->id)
        ->call('undo')
        ->assertSee('Second Card')
        ->call('undo')
        ->assertSee('First Card');

    expect($first->fresh()->status)->toBe(RecipeStatus::Pending);
});

it('undo brings back a card that was skipped and then decided', function () {
    [$first, $second, $third] = deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('skip', $first->id)
        ->call('approve', $second->id)
        ->call('approve', $third->id)
        ->call('reject', $first->id)
        ->call('undo')
        ->assertSee('First Card')
        ->assertSee('1 to go');
});

it('undo leaves a recipe alone that changed elsewhere since', function () {
    [$first] = deckOfThree();

    $page = Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('reject', $first->id);

    $first->update(['status' => RecipeStatus::Approved]);

    $page->call('undo')->assertDontSee('First Card');

    expect($first->fresh()->status)->toBe(RecipeStatus::Approved);
});

it('undo with nothing decided does nothing', function () {
    deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('undo')
        ->assertSee('First Card')
        ->assertSee('3 to go');
});

it('offers undo on the all caught up screen after the last decision', function () {
    $only = Recipe::factory()->create(['title' => 'Last Card']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertDontSee('Undo the last one')
        ->call('approve', $only->id)
        ->assertSee('All caught up')
        ->assertSee('Undo the last one');
});

it('skip ignores a recipe that is no longer pending', function () {
    [$first] = deckOfThree();
    $first->update(['status' => RecipeStatus::Approved]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('skip', $first->id)
        ->call('undo')
        ->assertSee('Second Card')
        ->assertSee('2 to go');
});

it('shows the skip and undo buttons on the card', function () {
    deckOfThree();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSeeHtml('aria-label="Skip for now"')
        ->assertSeeHtml('aria-label="Undo"')
        ->assertSeeHtml('aria-label="Approve"')
        ->assertSeeHtml('aria-label="Reject"');
});

it('puts the ingredient lines and the missing tool fixes in the sheet on the card', function () {
    $recipe = Recipe::factory()->create(['title' => 'Sheet Card']);
    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'red lentils'])->id,
        ['qty' => 250, 'unit' => 'g', 'note' => null],
    );
    $recipe->recipeTools()->each(fn ($tool) => $tool->delete());
    $recipe->recipeTools()->create(['position' => 1, 'count' => 1])
        ->alternatives()->create(['position' => 1, 'word' => 'tagine', 'kitchen_tool_kind_id' => null]);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSeeInOrder(['red lentils', '250 g'])
        ->assertSee('Add as a new kitchen tool')
        ->assertDontSee('KEEP')
        ->assertDontSee('NOPE');
});
