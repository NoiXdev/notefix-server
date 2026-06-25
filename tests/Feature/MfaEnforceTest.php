<?php

use App\Models\Setting;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('redirects a user without 2FA to security when enforced', function () {
    Setting::put('mfaEnforced', 'true');

    $this->actingAs(User::factory()->create())
        ->get('/docs/api')->assertRedirect('/security');
});

it('does not redirect when enforce is off', function () {
    Setting::put('mfaEnforced', 'false');

    $this->actingAs(User::factory()->create())
        ->get('/docs/api')->assertOk();
});

it('still lets an enforced user reach the security page', function () {
    Setting::put('mfaEnforced', 'true');

    $this->actingAs(User::factory()->create())
        ->get('/security')->assertOk();
});

it('lets a user with confirmed 2FA through when enforced', function () {
    Setting::put('mfaEnforced', 'true');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/user/two-factor-authentication');
    $secret = decrypt($user->fresh()->two_factor_secret);
    $this->post('/user/confirmed-two-factor-authentication', [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ]);

    $this->actingAs($user->fresh())->get('/docs/api')->assertOk();
});
