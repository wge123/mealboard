<?php

use App\Livewire\Preferences;
use App\Models\User;
use App\Support\HouseholdPreferences;
use Livewire\Livewire;

it('renders the preferences section with the stored values for a signed-in household', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/preferences')
        ->assertOk()
        ->assertSeeLivewire(Preferences::class)
        ->assertSee('Weekday limits')
        ->assertSee('Household size')
        ->assertSee('Avoided ingredients');

    Livewire::actingAs(User::factory()->create())
        ->test(Preferences::class)
        ->assertSet('weekdayMinutes', 30)
        ->assertSet('weekdayIngredients', 10)
        ->assertSet('householdSize', 2);
});

it('redirects guests to login', function () {
    $this->get('/settings/preferences')->assertRedirect('/login');
});

it('saves the weekday limits and the household size', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Preferences::class)
        ->set('weekdayMinutes', 45)
        ->set('weekdayIngredients', 12)
        ->call('saveLimits')
        ->assertHasNoErrors()
        ->set('householdSize', 4)
        ->call('saveHouseholdSize')
        ->assertHasNoErrors();

    $preferences = new HouseholdPreferences;

    expect($preferences->weekdayLimits())->toBe(['minutes' => 45, 'ingredients' => 12])
        ->and($preferences->householdSize())->toBe(4);
});

it('refuses a limit or size that is not a whole number of at least 1', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Preferences::class)
        ->set('weekdayMinutes', 0)
        ->set('weekdayIngredients', 'ten')
        ->call('saveLimits')
        ->assertHasErrors(['weekdayMinutes' => 'min', 'weekdayIngredients' => 'integer'])
        ->set('householdSize', '')
        ->call('saveHouseholdSize')
        ->assertHasErrors(['householdSize' => 'required']);

    expect((new HouseholdPreferences)->weekdayLimits())->toBe(['minutes' => 30, 'ingredients' => 10])
        ->and((new HouseholdPreferences)->householdSize())->toBe(2);
});

it('adds and removes an avoided ingredient', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(Preferences::class)
        ->set('newAvoided', ' Anchovies ')
        ->call('avoid')
        ->assertHasNoErrors()
        ->assertSet('newAvoided', '')
        ->assertSee('anchovies');

    expect((new HouseholdPreferences)->avoidedIngredients()->all())->toBe(['anchovies']);

    $component->call('stopAvoiding', 'anchovies')->assertDontSee('anchovies');

    expect((new HouseholdPreferences)->avoidedIngredients()->all())->toBe([]);
});

it('shows the refusal when the avoided word is blank or already avoided', function (string $word) {
    (new HouseholdPreferences)->avoid('cilantro');

    Livewire::actingAs(User::factory()->create())
        ->test(Preferences::class)
        ->set('newAvoided', $word)
        ->call('avoid')
        ->assertHasErrors('newAvoided');

    expect((new HouseholdPreferences)->avoidedIngredients()->all())->toBe(['cilantro']);
})->with(['  ', 'CILANTRO']);

it('renders every settings section in the menu', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/preferences')
        ->assertOk()
        ->assertSeeInOrder(['Kitchen tools', 'Preferences', 'Pantry staples', 'Channels']);
});
