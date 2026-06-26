<?php

use App\Filament\Pages\InstanceSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstanceConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('defaults the storage disk to local and returns the set value', function () {
    expect(InstanceConfig::storageDisk())->toBe('local');

    InstanceConfig::set('storage.disk', 's3');
    expect(InstanceConfig::storageDisk())->toBe('s3');
});

it('encrypts the s3 secret at rest', function () {
    InstanceConfig::set('storage.s3.secret', 'aws-secret');

    expect(Setting::get('storage.s3.secret'))->not->toBe('aws-secret')
        ->and(InstanceConfig::get('storage.s3.secret'))->toBe('aws-secret');
});

it('applies s3 settings into the disk config, only for set keys', function () {
    config(['filesystems.disks.s3.region' => 'env-region']);

    InstanceConfig::set('storage.s3.key', 'AKIA123');
    InstanceConfig::set('storage.s3.bucket', 'notes-bucket');
    InstanceConfig::set('storage.s3.endpoint', 'https://minio.example.test');
    InstanceConfig::set('storage.s3.path_style', 'true');

    InstanceConfig::applyFilesystem();

    expect(config('filesystems.disks.s3.key'))->toBe('AKIA123')
        ->and(config('filesystems.disks.s3.bucket'))->toBe('notes-bucket')
        ->and(config('filesystems.disks.s3.endpoint'))->toBe('https://minio.example.test')
        ->and(config('filesystems.disks.s3.use_path_style_endpoint'))->toBeTrue()
        ->and(config('filesystems.disks.s3.region'))->toBe('env-region'); // untouched
});

it('writes uploads to the configured disk', function () {
    Storage::fake('s3');
    InstanceConfig::set('storage.disk', 's3');

    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);

    $path = 'ab/cd/img.png';
    $this->postJson("/api/workspaces/{$ws->id}/images", [
        'path' => $path,
        'file' => UploadedFile::fake()->image('img.png', 8, 8),
    ])->assertOk();

    Storage::disk('s3')->assertExists("images/{$ws->id}/{$path}");
    Storage::disk('local')->assertMissing("images/{$ws->id}/{$path}");
});

it('passes the storage connection test on the configured disk', function () {
    Storage::fake('local');
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    actingAs($admin);
    Livewire::test(InstanceSettings::class)
        ->callAction('testStorage')
        ->assertNotified('Storage connection OK');
});
