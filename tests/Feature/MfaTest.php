<?php

use App\Models\Setting;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    Setting::put('setupComplete', 'true');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows the security page to an authenticated user', function () {
    $this->get('/security')->assertOk()->assertSee('Two-factor');
});

it('redirects guests away from the security page', function () {
    auth()->logout();

    $this->get('/security')->assertRedirect('/login');
});

it('lets a user enable and confirm two-factor', function () {
    $this->post('/user/two-factor-authentication');

    $this->user->refresh();
    expect($this->user->two_factor_secret)->not->toBeNull()
        ->and($this->user->two_factor_confirmed_at)->toBeNull();

    $secret = decrypt($this->user->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post('/user/confirmed-two-factor-authentication', ['code' => $code]);

    expect($this->user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});
