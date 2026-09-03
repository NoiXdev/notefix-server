<?php

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceVaultInvite;
use Illuminate\Support\Str;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('prunes invitations expired for longer than the grace period, wraps included', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $mk = fn (array $a) => Invitation::create(array_merge(
        ['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now()],
        $a,
    ));
    $old = $mk(['expires_at' => now()->subDays(8)]);
    WorkspaceVaultInvite::create(['invitation_id' => $old->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped_invite' => 'w', 'dek_check' => 'c']);
    $recent = $mk(['expires_at' => now()->subDays(2)]);
    $open = $mk(['expires_at' => now()->addDay()]);
    $never = $mk([]);
    $accepted = $mk(['expires_at' => now()->subDays(30), 'accepted_at' => now()->subDays(31), 'accepted_by' => $owner->id]);

    $this->artisan('invitations:prune')->expectsOutputToContain('Pruned 1')->assertSuccessful();

    expect(Invitation::whereKey($old->id)->exists())->toBeFalse()
        ->and(WorkspaceVaultInvite::where('invitation_id', $old->id)->exists())->toBeFalse()
        ->and(Invitation::whereIn('id', [$recent->id, $open->id, $never->id, $accepted->id])->count())->toBe(4);
});

it('honours a custom grace period', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $inv = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now(), 'expires_at' => now()->subDays(2)]);

    $this->artisan('invitations:prune', ['--days' => 1])->expectsOutputToContain('Pruned 1')->assertSuccessful();
    expect(Invitation::whereKey($inv->id)->exists())->toBeFalse();
});

it('is scheduled daily', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('invitations:prune')->assertSuccessful();
});
