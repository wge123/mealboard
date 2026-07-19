<?php

use App\Enums\RecipeStatus;
use App\Livewire\RecipeApproval;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests to login', function () {
    $this->get('/approve')->assertRedirect('/login');
});

it('shows the oldest pending recipe one card at a time', function () {
    Recipe::factory()->create(['title' => 'Older Pending Bowl', 'created_at' => now()->subDay()]);
    Recipe::factory()->create(['title' => 'Newer Pending Bake']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('Older Pending Bowl')
        ->assertDontSee('Newer Pending Bake');
});

it('shows the video link and thumbnail for a youtube source', function () {
    $recipe = Recipe::factory()->create([
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);
    $recipe->ingredients()->attach(
        Ingredient::factory()->create(['name' => 'salmon fillets'])->id,
        ['qty' => 2, 'unit' => null, 'note' => null],
    );

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
        ->assertSee('Watch on YouTube')
        ->assertSee('salmon fillets');
});

it('approve stamps status, approved_at, and approved_by', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    Livewire::actingAs($user)
        ->test(RecipeApproval::class)
        ->call('approve', $recipe->id);

    $recipe->refresh();

    expect($recipe->status)->toBe(RecipeStatus::Approved)
        ->and($recipe->approved_at)->not->toBeNull()
        ->and($recipe->approved_by)->toBe($user->id);
});

it('reject keeps the row with status rejected for discovery dedupe', function () {
    $recipe = Recipe::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->call('reject', $recipe->id);

    $kept = Recipe::find($recipe->id);

    expect($kept)->not->toBeNull()
        ->and($kept->status)->toBe(RecipeStatus::Rejected)
        ->and($kept->approved_at)->toBeNull()
        ->and($kept->approved_by)->toBeNull();
});

it('advances to the next pending card after a decision', function () {
    $first = Recipe::factory()->create(['title' => 'First In Queue', 'created_at' => now()->subDay()]);
    Recipe::factory()->create(['title' => 'Second In Queue']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('First In Queue')
        ->call('approve', $first->id)
        ->assertSee('Second In Queue')
        ->assertDontSee('First In Queue');
});

it('renders the empty state when nothing is pending', function () {
    Recipe::factory()->approved()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeApproval::class)
        ->assertSee('All caught up')
        ->assertSee('Nothing pending to review.');
});
