<?php

use App\Filament\Pages\InstanceSettings;
use App\Jobs\MigrateImageStorage;
use App\Models\Image;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('copies an image from the source disk to the target disk', function () {
    Storage::fake('local');
    Storage::fake('s3');
    $key = 'images/1/ab/cd/img.png';
    Storage::disk('local')->put($key, 'PNGDATA');

    (new MigrateImageStorage(1, 'ab/cd/img.png', 'local', 's3'))->handle();

    Storage::disk('s3')->assertExists($key);
    expect(Storage::disk('s3')->get($key))->toBe('PNGDATA');
    Storage::disk('local')->assertExists($key); // source kept
});

it('is idempotent when the target already holds a same-sized copy', function () {
    Storage::fake('local');
    Storage::fake('s3');
    $key = 'images/1/ab/cd/img.png';
    Storage::disk('local')->put($key, 'AAAA');
    Storage::disk('s3')->put($key, 'BBBB'); // same size, different content

    (new MigrateImageStorage(1, 'ab/cd/img.png', 'local', 's3'))->handle();

    // Skipped — target untouched.
    expect(Storage::disk('s3')->get($key))->toBe('BBBB');
});

it('does not fail when the source object is missing', function () {
    Storage::fake('local');
    Storage::fake('s3');

    (new MigrateImageStorage(1, 'gone.png', 'local', 's3'))->handle();

    expect(Storage::disk('s3')->exists('images/1/gone.png'))->toBeFalse();
});

it('dispatches a batch with one job per image and records the batch id', function () {
    Bus::fake();
    $user = User::factory()->create();
    $admin = $user;
    $admin->forceFill(['is_admin' => true])->save();
    $ws = $user->ownedWorkspaces->first();

    Image::create(['workspace_id' => $ws->id, 'path' => 'a/1.png', 'size' => 4, 'hash' => 'h1', 'created_at' => now()]);
    Image::create(['workspace_id' => $ws->id, 'path' => 'a/2.png', 'size' => 4, 'hash' => 'h2', 'created_at' => now()]);

    actingAs($admin);
    Livewire::test(InstanceSettings::class)
        ->callAction('migrateStorage', ['target' => 's3']);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2
        && $batch->jobs->first() instanceof MigrateImageStorage
        && $batch->name === 'image-storage-migration');

    expect(Setting::get('storage.migration.status'))->toBe('running')
        ->and(Setting::get('storage.migration.batch_id'))->not->toBeNull();
});

it('gates Horizon to admins only', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $plain = User::factory()->create();

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($plain)->allows('viewHorizon'))->toBeFalse();
});

it('defines a redis queue connection', function () {
    expect(config('queue.connections.redis.driver'))->toBe('redis');
});
