<?php

use App\Livewire\RecipeSiteSettings;
use App\Models\RecipeSite;
use App\Models\User;
use Livewire\Livewire;

it('renders the recipe site list on the settings page', function () {
    RecipeSite::factory()->create(['name' => 'Love and Lemons']);

    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertSeeLivewire(RecipeSiteSettings::class)
        ->assertSee('Love and Lemons');
});

it('adds a recipe site', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->set('feedUrl', 'https://www.loveandlemons.com/feed/')
        ->set('name', 'Love and Lemons')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('feedUrl', '')
        ->assertSet('name', '');

    $site = RecipeSite::firstWhere('feed_url', 'https://www.loveandlemons.com/feed/');

    expect($site)->not->toBeNull()
        ->and($site->name)->toBe('Love and Lemons')
        ->and($site->active)->toBeTrue();
});

it('refuses an invalid feed address and a blank name', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->set('feedUrl', 'not an address')
        ->set('name', '')
        ->call('add')
        ->assertHasErrors(['feedUrl' => 'url', 'name' => 'required']);

    expect(RecipeSite::count())->toBe(0);
});

it('refuses a duplicate feed address', function () {
    RecipeSite::factory()->create(['feed_url' => 'https://example.com/feed/']);

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->set('feedUrl', 'https://example.com/feed/')
        ->set('name', 'Example Again')
        ->call('add')
        ->assertHasErrors(['feedUrl' => 'unique']);

    expect(RecipeSite::count())->toBe(1);
});

it('pauses and resumes a recipe site', function () {
    $site = RecipeSite::factory()->create();

    $component = Livewire::actingAs(User::factory()->create())->test(RecipeSiteSettings::class);

    $component->call('toggle', $site->id);
    expect($site->refresh()->active)->toBeFalse();

    $component->call('toggle', $site->id);
    expect($site->refresh()->active)->toBeTrue();
});

it('removes a recipe site', function () {
    $site = RecipeSite::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->call('remove', $site->id);

    expect(RecipeSite::count())->toBe(0);
});

it('tells the settings menu when a recipe site is added, paused or removed', function () {
    $site = RecipeSite::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->call('toggle', $site->id)
        ->assertDispatched('settings-changed');

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->set('feedUrl', 'https://new.example.com/feed/')
        ->set('name', 'New One')
        ->call('add')
        ->assertDispatched('settings-changed');

    Livewire::actingAs(User::factory()->create())
        ->test(RecipeSiteSettings::class)
        ->call('remove', $site->id)
        ->assertDispatched('settings-changed');
});
