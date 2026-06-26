<?php

use App\Filament\Pages\InstanceSettings;
use App\Filament\Resources\Workspaces\Pages\EditWorkspace;
use App\Filament\Resources\Workspaces\RelationManagers\MembersRelationManager;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstanceConfig;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

function adminForActions(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('surfaces a failure when the test email cannot be sent', function () {
    InstanceConfig::set('mail.transport', 'bogus-mailer'); // unsupported → send throws

    actingAs(adminForActions());
    Livewire::test(InstanceSettings::class)
        ->callAction('sendTestEmail')
        ->assertNotified('Test email failed');
});

it('surfaces a failure when the storage connection cannot be reached', function () {
    InstanceConfig::set('storage.disk', 'bogus-disk'); // not a configured disk → throws

    actingAs(adminForActions());
    Livewire::test(InstanceSettings::class)
        ->callAction('testStorage')
        ->assertNotified('Storage test failed');
});

it('warns when migrating to the same disk', function () {
    actingAs(adminForActions()); // default disk is local

    Livewire::test(InstanceSettings::class)
        ->callAction('migrateStorage', ['target' => 'local'])
        ->assertNotified('Source and target are the same');
});

it('reports when there are no files to migrate', function () {
    actingAs(adminForActions());

    Livewire::test(InstanceSettings::class)
        ->callAction('migrateStorage', ['target' => 's3'])
        ->assertNotified('No files to migrate');
});

it('renders the workspace members relation manager', function () {
    $admin = adminForActions();
    $ws = $admin->ownedWorkspaces->first();

    actingAs($admin);
    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $ws,
        'pageClass' => EditWorkspace::class,
    ])->assertSuccessful();
});

it('exposes the invitation workspace relation', function () {
    expect((new Invitation)->workspace())->toBeInstanceOf(BelongsTo::class);
});

it('redirects to login from the register page when registration is disabled', function () {
    Setting::put('registrationEnabled', 'false');

    $this->get('/register')->assertRedirect('/login');
});
