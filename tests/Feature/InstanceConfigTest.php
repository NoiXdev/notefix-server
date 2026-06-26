<?php

use App\Models\Setting;
use App\Support\InstanceConfig;

it('round-trips a secret encrypted at rest', function () {
    InstanceConfig::set('mail.postal.key', 'super-secret');

    // Stored ciphertext is not the plaintext…
    expect(Setting::get('mail.postal.key'))->not->toBe('super-secret');
    // …but reads back decrypted.
    expect(InstanceConfig::get('mail.postal.key'))->toBe('super-secret');
});

it('treats a blank secret as keep-existing', function () {
    InstanceConfig::set('mail.smtp.password', 'pw1');
    InstanceConfig::set('mail.smtp.password', '');   // no-op
    InstanceConfig::set('mail.smtp.password', null); // no-op

    expect(InstanceConfig::get('mail.smtp.password'))->toBe('pw1');
});

it('stores non-secret values as plaintext', function () {
    InstanceConfig::set('mail.from_address', 'hi@example.test');

    expect(Setting::get('mail.from_address'))->toBe('hi@example.test')
        ->and(InstanceConfig::get('mail.from_address'))->toBe('hi@example.test');
});

it('overrides mail config only for keys that are set', function () {
    config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'env-host']);

    InstanceConfig::set('mail.transport', 'smtp');
    InstanceConfig::set('mail.from_address', 'hi@example.test');
    InstanceConfig::set('mail.from_name', 'Notefix');
    InstanceConfig::set('mail.smtp.host', 'mail.example.test');
    InstanceConfig::set('mail.smtp.port', '587');

    InstanceConfig::applyMail();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.from.address'))->toBe('hi@example.test')
        ->and(config('mail.from.name'))->toBe('Notefix')
        ->and(config('mail.mailers.smtp.host'))->toBe('mail.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe('587');
});

it('leaves config untouched when no settings exist', function () {
    config(['mail.default' => 'log']);
    InstanceConfig::applyMail();
    expect(config('mail.default'))->toBe('log');
});

it('falls back to the default when a secret cannot be decrypted', function () {
    // A secret key holding non-ciphertext (e.g. a value written outside the app).
    Setting::put('mail.postal.key', 'not-encrypted');

    expect(InstanceConfig::get('mail.postal.key', 'fallback'))->toBe('fallback');
});
