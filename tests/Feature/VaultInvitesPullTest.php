<?php

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultInvite;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('shows an owner the open invitations with their wrap generation, and members nothing', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 2])->save();
    $withWrap = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now()]);
    WorkspaceVaultInvite::create(['invitation_id' => $withWrap->id, 'generation' => 1, 'kdf_params' => [], 'dek_wrapped_invite' => 'iw', 'dek_check' => 'c']);
    $noWrap = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'viewer', 'invited_by' => $owner->id, 'created_at' => now()]);
    Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'viewer', 'invited_by' => $owner->id, 'created_at' => now(), 'expires_at' => now()->subDay()]);
    $joined = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $joined->id, 'role' => 'editor']);
    Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now(), 'accepted_at' => now(), 'accepted_by' => $joined->id]);

    Passport::actingAs($owner);
    $body = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json();
    expect($body['workspaceRole'])->toBe('owner')
        ->and($body['vaultInvites'])->toBe([
            ['invitationId' => $withWrap->id, 'generation' => 1],
            ['invitationId' => $noWrap->id, 'generation' => null],
        ]);

    Passport::actingAs($joined);
    $body = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json();
    expect($body['workspaceRole'])->toBe('editor')
        ->and($body['vaultInvites'])->toBe([]);
});
