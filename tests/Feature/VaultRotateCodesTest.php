<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use App\Models\WorkspaceVaultRotationWrap;
use Laravel\Passport\Passport;

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
        ['userId' => $owner->id, 'role' => 'owner'],
        ['userId' => $member->id, 'role' => 'viewer'],
    ]);

    Passport::actingAs($stranger);
    $this->getJson("/api/workspaces/{$ws->id}/members")->assertStatus(403);
});
