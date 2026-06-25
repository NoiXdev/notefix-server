<?php

use App\Models\Setting;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('shows the branded landing page with the instance name', function () {
    Setting::put('instanceName', 'Acme Notes');

    $this->get('/')->assertOk()
        ->assertSee('Acme Notes')
        ->assertSee('Sign in');
});

it('shows the register link when registration is enabled', function () {
    Setting::put('registrationEnabled', 'true');

    $this->get('/')->assertOk()->assertSee('Create account');
});

it('hides the register link when registration is disabled', function () {
    Setting::put('registrationEnabled', 'false');

    $this->get('/')->assertOk()->assertDontSee('Create account');
});
