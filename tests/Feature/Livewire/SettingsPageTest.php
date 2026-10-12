<?php

use App\Livewire\ChannelSettings;
use App\Livewire\KitchenTools;
use App\Livewire\PantryStaples;
use App\Livewire\Preferences;
use App\Models\User;

it('shows every settings section on one page, kitchen tools first', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertSeeLivewire(KitchenTools::class)
        ->assertSeeLivewire(Preferences::class)
        ->assertSeeLivewire(PantryStaples::class)
        ->assertSeeLivewire(ChannelSettings::class)
        ->assertSeeInOrder(['id="tools"', 'id="preferences"', 'id="pantry"', 'id="channels"'], false);
});

it('links each section from the settings menu', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertSee('aria-label="Settings sections"', false)
        ->assertSeeInOrder(['href="#tools"', 'href="#preferences"', 'href="#pantry"', 'href="#channels"'], false);
});

it('redirects guests away from settings', function () {
    $this->get('/settings')->assertRedirect('/login');
});

it('sends the old section addresses to their section on the settings page', function (string $old, string $anchor) {
    $this->actingAs(User::factory()->create())
        ->get($old)
        ->assertStatus(301)
        ->assertRedirect('/settings#'.$anchor);
})->with([
    ['/settings/kitchen-tools', 'tools'],
    ['/settings/preferences', 'preferences'],
    ['/settings/pantry-staples', 'pantry'],
    ['/settings/channels', 'channels'],
]);

it('points the Settings link in the top navigation at the settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/plan')
        ->assertOk()
        ->assertSeeInOrder(['href="'.route('settings').'"', 'Settings'], false);
});
