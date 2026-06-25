<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    Setting::put('setupComplete', 'true'); // a set-up instance
});

it('redirects guests from the API docs to the login page', function () {
    $this->get('/docs/api')->assertRedirect('/login');
});

it('shows the API docs to an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/docs/api')
        ->assertOk()
        ->assertSee('Sync API');
});

it('logs a user in with valid credentials', function () {
    $user = User::factory()->create(); // factory password is "password"

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/docs/api');

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
