<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('lets a user register when registration is enabled', function () {
    Setting::put('registrationEnabled', 'true');

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
});

it('redirects the register page to login when disabled', function () {
    Setting::put('registrationEnabled', 'false');

    $this->get('/register')->assertRedirect('/login');
});

it('rejects a registration POST when disabled', function () {
    Setting::put('registrationEnabled', 'false');

    $this->post('/register', [
        'name' => 'Blocked',
        'email' => 'blocked@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'blocked@example.com')->exists())->toBeFalse();
});

it('lets an admin open the instance settings page', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get('/admin/instance-settings')->assertOk();
});
