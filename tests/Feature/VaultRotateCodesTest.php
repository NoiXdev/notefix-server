<?php

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultInvite;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use App\Models\WorkspaceVaultRotationWrap;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

function rotKeyFor(int $userId, int $gen, string $kind = 'own'): array
{
    return ['userId' => $userId, 'kind' => $kind, 'kdfParams' => ['salt' => "s{$userId}"], 'dekWrapped' => "w{$userId}-{$gen}", 'dekCheck' => 'c'];
}

it('stores an own key for the rotating owner and rotation wraps for the others, still rejecting a wrong member set', function () {
    $owner = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    foreach ([$a, $b] as $u) {
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'role' => 'editor']);
    }
    $ws->forceFill(['vault_generation' => 1])->save();
    foreach ([$owner, $a, $b] as $u) {
        WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    }
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    Passport::actingAs($owner);
    // Wrong member set (a stranger instead of b) → 422, nothing written for generation 2.
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", [
        'generation' => 2,
        'keys' => [rotKeyFor($owner->id, 2, 'own'), rotKeyFor($a->id, 2, 'code'), rotKeyFor($stranger->id, 2, 'code')],
        'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'],
    ])->assertStatus(422);
    expect(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('generation', 2)->exists())->toBeFalse()
        ->and(WorkspaceVaultRotationWrap::where('workspace_id', $ws->id)->where('generation', 2)->exists())->toBeFalse();

    // Correct member set, mixed kinds → 201.
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", [
        'generation' => 2,
        'keys' => [rotKeyFor($owner->id, 2, 'own'), rotKeyFor($a->id, 2, 'code'), rotKeyFor($b->id, 2, 'code')],
        'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'],
    ])->assertCreated()->assertJsonPath('vaultGeneration', 2);

    expect(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('user_id', $owner->id)->where('generation', 2)->exists())->toBeTrue()
        ->and(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('user_id', $a->id)->where('generation', 2)->exists())->toBeFalse()
        ->and(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('user_id', $b->id)->where('generation', 2)->exists())->toBeFalse()
        ->and(WorkspaceVaultRotationWrap::where('workspace_id', $ws->id)->where('user_id', $a->id)->where('generation', 2)->first()?->dek_wrapped)->toBe("w{$a->id}-2")
        ->and(WorkspaceVaultRotationWrap::where('workspace_id', $ws->id)->where('user_id', $b->id)->where('generation', 2)->first()?->dek_wrapped)->toBe("w{$b->id}-2");
});

it("shows the member's rotation wrap on pull, with mine unchanged for the new generation", function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
    $ws->forceFill(['vault_generation' => 1])->save();
    foreach ([$owner, $member] as $u) {
        WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    }
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", [
        'generation' => 2,
        'keys' => [rotKeyFor($owner->id, 2, 'own'), rotKeyFor($member->id, 2, 'code')],
        'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'],
    ])->assertCreated();

    Passport::actingAs($member);
    $vaultKeys = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json('vaultKeys');
    expect(collect($vaultKeys['mine'])->pluck('generation')->all())->toBe([1])
        ->and($vaultKeys['rotation'])->toHaveCount(1)
        ->and($vaultKeys['rotation'][0])->toMatchArray(['generation' => 2, 'dekWrapped' => "w{$member->id}-2", 'dekCheck' => 'c']);
});

it('converts a rotation wrap into a key row on PUT keys/me, and rewraps in place on a second PUT', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
    $ws->forceFill(['vault_generation' => 2])->save();
    WorkspaceVaultRotationWrap::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'generation' => 2, 'kdf_params' => ['salt' => 'code'], 'dek_wrapped' => 'coded==', 'dek_check' => 'c']);

    Passport::actingAs($member);
    // No key row and no wrap for generation 5 → 404, unchanged.
    $this->putJson("/api/workspaces/{$ws->id}/vault/keys/me", ['generation' => 5, 'kdfParams' => ['salt' => 'x'], 'dekWrapped' => 'x==', 'dekCheck' => 'c'])
        ->assertStatus(404);

    $this->putJson("/api/workspaces/{$ws->id}/vault/keys/me", ['generation' => 2, 'kdfParams' => ['salt' => 'mine'], 'dekWrapped' => 'mywrap==', 'dekCheck' => 'c'])
        ->assertOk()->assertJson(['ok' => true]);

    expect(WorkspaceVaultRotationWrap::where('workspace_id', $ws->id)->where('user_id', $member->id)->where('generation', 2)->exists())->toBeFalse()
        ->and(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('user_id', $member->id)->where('generation', 2)->first()?->dek_wrapped)->toBe('mywrap==');

    // Second PUT rewraps the now-existing key row in place, not a new row.
    $this->putJson("/api/workspaces/{$ws->id}/vault/keys/me", ['generation' => 2, 'kdfParams' => ['salt' => 'again'], 'dekWrapped' => 'mywrap2==', 'dekCheck' => 'c'])
        ->assertOk()->assertJson(['ok' => true]);
    expect(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('user_id', $member->id)->where('generation', 2)->count())->toBe(1)
        ->and(WorkspaceVaultKey::where('workspace_id', $ws->id)->where('user_id', $member->id)->where('generation', 2)->first()->dek_wrapped)->toBe('mywrap2==');
});

it('lets only the vault creator add the missing recovery wrap for a generation, once', function () {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $coOwner->id, 'role' => 'owner']);
    $ws->forceFill(['vault_generation' => 2])->save();
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    // Non-creator (co-owner) → 403.
    Passport::actingAs($coOwner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/recovery", ['generation' => 2, 'recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'])->assertStatus(403);

    Passport::actingAs($owner);
    // Generation beyond the current vault generation → 422.
    $this->postJson("/api/workspaces/{$ws->id}/vault/recovery", ['generation' => 3, 'recoverySalt' => 's', 'dekWrappedRecovery' => 'r3', 'dekCheck' => 'c'])->assertStatus(422);

    // Creator, valid generation → 201.
    $this->postJson("/api/workspaces/{$ws->id}/vault/recovery", ['generation' => 2, 'recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'])
        ->assertCreated()->assertJson(['ok' => true]);
    expect(WorkspaceVaultRecovery::where('workspace_id', $ws->id)->where('generation', 2)->value('created_by'))->toBe($owner->id);

    // Duplicate for the same generation → 409.
    $this->postJson("/api/workspaces/{$ws->id}/vault/recovery", ['generation' => 2, 'recoverySalt' => 's', 'dekWrappedRecovery' => 'r2b', 'dekCheck' => 'c'])->assertStatus(409);
});

it('lists members with roles for a member, and 403s a stranger', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'viewer']);

    Passport::actingAs($member);
    $listed = $this->getJson("/api/workspaces/{$ws->id}/members")->assertOk()->json('members');
    expect($listed)->toBe([
        ['userId' => $owner->id, 'role' => 'owner', 'name' => $owner->name],
        ['userId' => $member->id, 'role' => 'viewer', 'name' => $member->name],
    ]);

    $member->forceFill(['name' => ''])->save();
    $again = $this->getJson("/api/workspaces/{$ws->id}/members")->assertOk()->json('members');
    expect($again)->toBe([
        ['userId' => $owner->id, 'role' => 'owner', 'name' => $owner->name],
        ['userId' => $member->id, 'role' => 'viewer', 'name' => ''],
    ]);

    Passport::actingAs($stranger);
    $this->getJson("/api/workspaces/{$ws->id}/members")->assertStatus(403);
});

it('403s an owner whose member row was deleted, on members and keys/me', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $coOwner = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $coOwner->id, 'role' => 'owner']);
    // Simulate a stale owner_id: the row that made them a member is gone,
    // but the workspace's owner_id column still points at them.
    WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $owner->id)->delete();

    Passport::actingAs($owner);
    $this->getJson("/api/workspaces/{$ws->id}/members")->assertStatus(403);
    $this->putJson("/api/workspaces/{$ws->id}/vault/keys/me", ['generation' => 1, 'kdfParams' => ['salt' => 'x'], 'dekWrapped' => 'w', 'dekCheck' => 'c'])
        ->assertStatus(403);
});

it('rotates on exactly the member rows even when owner_id points at a removed user', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $coOwner = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $coOwner->id, 'role' => 'owner']);
    WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $owner->id)->delete();
    $ws->forceFill(['vault_generation' => 1])->save();
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $coOwner->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $coOwner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    Passport::actingAs($coOwner);
    // Only $coOwner's key is supplied — the stale owner_id must not be required.
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", [
        'generation' => 2,
        'keys' => [rotKeyFor($coOwner->id, 2, 'own')],
        'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'],
    ])->assertCreated()->assertJsonPath('vaultGeneration', 2);
});

it('removal deletes both the vault key and any pending rotation wrap for that member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $mb = WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
    $ws->forceFill(['vault_generation' => 2])->save();
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    WorkspaceVaultRotationWrap::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'generation' => 2, 'kdf_params' => [], 'dek_wrapped' => 'code', 'dek_check' => 'c']);
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $member->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'theirs', 'dek_check' => 'c']);
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 2, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'mine', 'dek_check' => 'c']);

    actingAs($owner)->delete(route('workspaces.members.destroy', [$ws, $mb]))->assertRedirect();

    expect(WorkspaceVaultKey::where('user_id', $member->id)->exists())->toBeFalse()
        ->and(WorkspaceVaultRotationWrap::where('user_id', $member->id)->exists())->toBeFalse()
        ->and(WorkspaceVaultRecovery::where('workspace_id', $ws->id)->where('created_by', $member->id)->exists())->toBeFalse()
        ->and(WorkspaceVaultRecovery::where('workspace_id', $ws->id)->where('created_by', $owner->id)->exists())->toBeTrue();
});

it('deletes stale invite wraps on rotation so the owner can attach a fresh one', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 1])->save();
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $owner->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);
    $inv = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now()]);
    WorkspaceVaultInvite::create(['invitation_id' => $inv->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped_invite' => 'iw', 'dek_check' => 'c']);

    // Create an accepted invitation with a wrap
    $joined = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $joined->id, 'role' => 'editor']);
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $joined->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    $accepted = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now(), 'accepted_at' => now(), 'accepted_by' => $joined->id]);
    WorkspaceVaultInvite::create(['invitation_id' => $accepted->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped_invite' => 'kept', 'dek_check' => 'c']);

    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", [
        'generation' => 2,
        'keys' => [rotKeyFor($owner->id, 2, 'own'), rotKeyFor($joined->id, 2, 'code')],
        'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'],
    ])->assertCreated();

    expect(WorkspaceVaultInvite::where('invitation_id', $inv->id)->exists())->toBeFalse()
        ->and(WorkspaceVaultInvite::where('invitation_id', $accepted->id)->value('dek_wrapped_invite'))->toBe('kept');

    // The invitation is still open, so the owner attaches a fresh wrap for the new generation.
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$inv->id}", [
        'generation' => 2, 'kdfParams' => ['salt' => 'x'], 'dekWrapped' => 'new==', 'dekCheck' => 'c',
    ])->assertCreated();
});

it('422s a rotation whose keys repeat a userId', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 1])->save();
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $owner->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped' => 'w', 'dek_check' => 'c']);
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/rotate", [
        'generation' => 2,
        'keys' => [rotKeyFor($owner->id, 2, 'own'), rotKeyFor($owner->id, 2, 'code')],
        'recovery' => ['recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'],
    ])->assertStatus(422);
});

it('403s a stranger posting a recovery wrap', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 1])->save();
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'r', 'dek_check' => 'c']);

    Passport::actingAs($stranger);
    $this->postJson("/api/workspaces/{$ws->id}/vault/recovery", ['generation' => 1, 'recoverySalt' => 's', 'dekWrappedRecovery' => 'r2', 'dekCheck' => 'c'])
        ->assertStatus(403);
});
