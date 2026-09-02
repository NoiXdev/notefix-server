<?php

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultInvite;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

function inviteWrap(int $gen = 1): array
{
    return ['generation' => $gen, 'kdfParams' => ['salt' => 'inv'], 'dekWrapped' => 'invwrap==', 'dekCheck' => 'check=='];
}

it('lets the owner attach a wrap, the invitee fetch it and swap in their own key', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 1])->save();
    $inv = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now()]);

    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$inv->id}", inviteWrap())->assertCreated();
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$inv->id}", inviteWrap())->assertStatus(409);
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$inv->id}", inviteWrap(2))->assertStatus(422);

    // The invitee joins (existing web flow) …
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $invitee->id, 'role' => 'editor']);
    $inv->update(['accepted_at' => now(), 'accepted_by' => $invitee->id]);

    Passport::actingAs($invitee);
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/{$inv->id}")->assertOk()->assertJsonPath('dekWrappedInvite', 'invwrap==');
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$inv->id}/accept", ['generation' => 1, 'kdfParams' => ['salt' => 'me'], 'dekWrapped' => 'mywrap==', 'dekCheck' => 'check=='])->assertCreated();
    expect(WorkspaceVaultInvite::where('invitation_id', $inv->id)->exists())->toBeFalse()
        ->and($ws->vaultKeys()->where('user_id', $invitee->id)->value('dek_wrapped'))->toBe('mywrap==');
});

it('refuses attaching to a closed invitation and accepting by the wrong user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ws->forceFill(['vault_generation' => 1])->save();
    $closed = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'viewer', 'invited_by' => $owner->id, 'created_at' => now(), 'expires_at' => now()->subDay()]);
    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$closed->id}", inviteWrap())->assertStatus(410);

    $open = Invitation::create(['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'viewer', 'invited_by' => $owner->id, 'created_at' => now()]);
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$open->id}", inviteWrap())->assertCreated();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $other->id, 'role' => 'viewer']);
    Passport::actingAs($other); // member, but not the invitee
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/{$open->id}")->assertNotFound();
    $this->postJson("/api/workspaces/{$ws->id}/vault/invites/{$open->id}/accept", inviteWrap())->assertNotFound();
});
