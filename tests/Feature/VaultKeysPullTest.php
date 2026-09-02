<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('delivers only the caller’s own key entries and the creator’s recovery entries', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $owner->id, 'generation' => 1, 'kdf_params' => ['salt' => 'a'], 'dek_wrapped' => 'w-owner', 'dek_check' => 'c']);
    WorkspaceVaultKey::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'generation' => 1, 'kdf_params' => ['salt' => 'b'], 'dek_wrapped' => 'w-member', 'dek_check' => 'c']);
    WorkspaceVaultRecovery::create(['workspace_id' => $ws->id, 'generation' => 1, 'created_by' => $owner->id, 'recovery_salt' => 's', 'dek_wrapped_recovery' => 'w-rec', 'dek_check' => 'c']);

    Passport::actingAs($owner);
    $mine = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json('vaultKeys');
    expect($mine['mine'])->toHaveCount(1)->and($mine['mine'][0]['dekWrapped'])->toBe('w-owner')
        ->and($mine['recovery'])->toHaveCount(1)->and($mine['recovery'][0]['dekWrappedRecovery'])->toBe('w-rec');

    Passport::actingAs($member);
    $theirs = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json('vaultKeys');
    expect($theirs['mine'][0]['dekWrapped'])->toBe('w-member')->and($theirs['recovery'])->toBe([]);
});
