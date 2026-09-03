<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use Laravel\Passport\Passport;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

function keyFor(int $userId, int $gen): array
{
    return ['userId' => $userId, 'kdfParams' => ['salt' => "s{$userId}"], 'dekWrapped' => "w{$userId}-{$gen}", 'dekCheck' => 'c'];
}

it('marks a rotation pending on removal and completes it with a full key set', function () {
    $owner = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    foreach ([$a, $b] as $u) {
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'role' => 'editor']);
    }
    $ws->forceFill(['vault_generation' => 1])->save();
    foreach ([$owner, $a, $b] as $u) {
        WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    }
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    // Existing web removal action (owner-managed members).
    $mb = WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $b->id)->firstOrFail();
    actingAs($owner)->delete(route('workspaces.members.destroy', [$ws, $mb]))->assertRedirect();
    expect(WorkspaceVaultKey::where('user_id', $b->id)->exists())->toBeFalse()
        ->and($ws->fresh()->vault_rotation_pending)->toBeTrue();

    Passport::actingAs($owner);
    // Wrong member set (still lists b) → 422.
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", ['generation' => 2, 'keys' => [keyFor($owner->id, 2), keyFor($a->id, 2), keyFor($b->id, 2)], 'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c']])->assertStatus(422);
    // Correct set → new generation, pending cleared, old keys kept.
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", ['generation' => 2, 'keys' => [keyFor($owner->id, 2), keyFor($a->id, 2)], 'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c']])->assertCreated()->assertJsonPath('vaultGeneration', 2);
    $fresh = $ws->fresh();
    expect($fresh->vault_generation)->toBe(2)->and($fresh->vault_rotation_pending)->toBeFalse()
        ->and(WorkspaceVaultKey::where('user_id', $a->id)->pluck('generation')->sort()->values()->all())->toBe([1, 2])
        ->and(WorkspaceVaultRecovery::where('workspace_id', $ws->id)->count())->toBe(2);
});

it('rejects a stale generation number', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 3])->save();
    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", ['generation' => 3, 'keys' => [keyFor($owner->id, 3)], 'recovery' => null])->assertStatus(422);
});

it('requires a recovery-set holder to supply a recovery wrap, but not a non-holder owner', function () {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $coOwner->id, 'role' => 'owner']);
    $ws->forceFill(['vault_generation' => 1])->save();
    foreach ([$owner, $coOwner] as $u) {
        WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    }
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    // (a) The recovery-set holder omitting recovery → 422.
    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", ['generation' => 2, 'keys' => [keyFor($owner->id, 2), keyFor($coOwner->id, 2)], 'recovery' => null])->assertStatus(422);

    // (b) A non-holder owner omitting recovery → succeeds, no new recovery row.
    Passport::actingAs($coOwner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", ['generation' => 2, 'keys' => [keyFor($owner->id, 2), keyFor($coOwner->id, 2)], 'recovery' => null])->assertCreated()->assertJsonPath('vaultGeneration', 2);
    expect($ws->fresh()->vault_generation)->toBe(2)
        ->and(WorkspaceVaultRecovery::where('workspace_id', $ws->id)->where('generation', 2)->exists())->toBeFalse();
});
