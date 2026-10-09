<?php

use App\Models\MealPlan;
use App\Models\User;

it('points the Shop tab at the latest locked week', function () {
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    $latest = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    MealPlan::factory()->create(['week_start_date' => '2026-07-27']); // draft

    $this->actingAs(User::factory()->create())
        ->get('/plan')
        ->assertOk()
        ->assertSee(route('plan.shopping-list', $latest->id), false);
});

it('hides the Shop tab when no week is locked', function () {
    MealPlan::factory()->create(); // draft only

    $this->actingAs(User::factory()->create())
        ->get('/plan')
        ->assertOk()
        ->assertDontSee('shopping-list');
});

it('shows a Settings link in the top navigation that opens the settings area', function () {
    $this->actingAs(User::factory()->create())
        ->get('/plan')
        ->assertOk()
        ->assertSeeInOrder(['href="'.route('settings.channels').'"', 'Settings'], false);
});

it('renders the settings section menu with Channels on the channels page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/channels')
        ->assertOk()
        ->assertSee('aria-label="Settings sections"', false)
        ->assertSee('aria-current="page"', false);
});

it('redirects guests away from the settings area', function () {
    $this->get('/settings/channels')->assertRedirect('/login');
});
