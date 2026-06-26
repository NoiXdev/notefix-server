<?php

use App\Filament\Pages\InstanceSettings;
use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstanceConfig;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

// is_admin is guarded (not mass-assignable) — set it via forceFill, matching FilamentAdminTest.
function mailAdminUser(array $attrs = []): User
{
    $admin = User::factory()->create($attrs);
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('saves mail settings and encrypts the secret', function () {
    $admin = mailAdminUser();

    actingAs($admin);
    Livewire::test(InstanceSettings::class)
        ->fillForm([
            'registrationEnabled' => true,
            'mfaEnforced' => false,
            'mail_transport' => 'postal',
            'mail_from_address' => 'noreply@example.test',
            'mail_from_name' => 'Notefix',
            'mail_postal_endpoint' => 'https://postal.example.test',
            'mail_postal_key' => 'secret-key',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(InstanceConfig::get('mail.transport'))->toBe('postal')
        ->and(InstanceConfig::get('mail.postal.endpoint'))->toBe('https://postal.example.test')
        ->and(InstanceConfig::get('mail.postal.key'))->toBe('secret-key')
        ->and(Setting::get('mail.postal.key'))->not->toBe('secret-key'); // encrypted at rest
});

it('keeps the existing secret when the key field is left blank', function () {
    $admin = mailAdminUser();
    InstanceConfig::set('mail.postal.key', 'original-key');

    actingAs($admin);
    Livewire::test(InstanceSettings::class)
        ->fillForm([
            'mail_transport' => 'postal',
            'mail_from_address' => 'noreply@example.test',
            'mail_postal_endpoint' => 'https://postal.example.test',
            'mail_postal_key' => '', // blank = keep
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(InstanceConfig::get('mail.postal.key'))->toBe('original-key');
});

it('sends a test email to the admin using saved settings', function () {
    Mail::fake();
    $admin = mailAdminUser(['email' => 'admin@example.test']);
    InstanceConfig::set('mail.transport', 'smtp');
    InstanceConfig::set('mail.from_address', 'noreply@example.test');

    actingAs($admin);
    Livewire::test(InstanceSettings::class)
        ->callAction('sendTestEmail');

    Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));
});
