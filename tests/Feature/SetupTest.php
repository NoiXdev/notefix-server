<?php

use App\Models\Setting;
use App\Models\User;

it('redirects a fresh instance to the setup wizard', function () {
    $this->get('/')->assertRedirect('/setup');
});

it('creates the first admin and completes setup', function () {
    $response = $this->post('/setup', [
        'instance_name' => 'My Notefix',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/admin');

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->is_admin)->toBeTrue()
        ->and($admin->ownedWorkspaces)->toHaveCount(1);

    expect(Setting::get('setupComplete'))->toBe('true')
        ->and(Setting::get('instanceName'))->toBe('My Notefix');
});

it('blocks the setup wizard once complete', function () {
    Setting::put('setupComplete', 'true');

    $this->get('/setup')->assertRedirect('/admin');
});

it('lets the home page through once setup is complete', function () {
    Setting::put('setupComplete', 'true');

    $this->get('/')->assertSuccessful();
});
