<?php

use App\Models\User;
use Database\Seeders\UserSeeder;

it('renders the login page', function () {
    $this->get('/login')->assertOk();
});

it('lets both seeded users log in', function (string $email) {
    $this->seed(UserSeeder::class);

    $user = User::where('email', $email)->firstOrFail();

    $response = $this->post('/login', [
        'email' => $email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
})->with([
    'willem@example.com',
    'partner@example.com',
]);

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'willem@example.com']);

    $response = $this->from('/login')->post('/login', [
        'email' => 'willem@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs an authenticated user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('has no registration route', function () {
    $this->get('/register')->assertNotFound();
});

it('renders the login card without the app navigation', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Log in')
        ->assertDontSee('Insights')
        ->assertDontSee('Settings');
});

it('still renders the 404 page on the bare layout', function () {
    $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found');
});
