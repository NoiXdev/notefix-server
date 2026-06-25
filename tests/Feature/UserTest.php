<?php

use App\Models\User;
use Laravel\Passport\Contracts\OAuthenticatable;

it('defaults is_admin to false for new users', function () {
    expect(User::factory()->create()->fresh()->is_admin)->toBeFalse();
});

it('casts is_admin to a boolean', function () {
    expect((new User)->forceFill(['is_admin' => 1])->is_admin)->toBeTrue();
});

it('is an OAuth authenticatable for Passport', function () {
    expect(new User)->toBeInstanceOf(OAuthenticatable::class);
});
