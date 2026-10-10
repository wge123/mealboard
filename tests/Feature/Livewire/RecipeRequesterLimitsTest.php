<?php

use App\Models\User;

it('names the weekday limits without hard-coding their numbers', function () {
    $this->actingAs(User::factory()->create())
        ->get('/request')
        ->assertOk()
        ->assertSee('weekday limits')
        ->assertDontSee('30-minute')
        ->assertDontSee('10-ingredient');
});
