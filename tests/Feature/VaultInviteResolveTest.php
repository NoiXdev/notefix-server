<?php

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('resolves an invitation id from its token for the owner and for the invitee', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $token = Str::random(40);
    $inv = Invitation::create(['workspace_id' => $ws->id, 'token' => $token, 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now()]);

    Passport::actingAs($owner);
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/by-token/{$token}")
        ->assertOk()->assertExactJson(['invitationId' => $inv->id]);

    // The invitee joins through the existing web flow, then resolves the same link.
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $invitee->id, 'role' => 'editor']);
    $inv->update(['accepted_at' => now(), 'accepted_by' => $invitee->id]);

    Passport::actingAs($invitee);
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/by-token/{$token}")
        ->assertOk()->assertExactJson(['invitationId' => $inv->id]);
});

it('hides the invitation from an unrelated member, an unknown token and another workspace', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $otherWs = $stranger->ownedWorkspaces->first();
    $token = Str::random(40);
    Invitation::create(['workspace_id' => $ws->id, 'token' => $token, 'role' => 'viewer', 'invited_by' => $owner->id, 'created_at' => now()]);

    // A member of the workspace who is neither the owner nor the invitee.
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $other->id, 'role' => 'viewer']);
    Passport::actingAs($other);
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/by-token/{$token}")->assertNotFound();

    Passport::actingAs($owner);
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/by-token/".Str::random(40))->assertNotFound();

    // The token exists, but belongs to a workspace the caller does not own.
    Passport::actingAs($stranger);
    $this->getJson("/api/workspaces/{$otherWs->id}/vault/invites/by-token/{$token}")->assertNotFound();
});

it('refuses a caller who is not a member at all', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $token = Str::random(40);
    Invitation::create(['workspace_id' => $ws->id, 'token' => $token, 'role' => 'viewer', 'invited_by' => $owner->id, 'created_at' => now()]);

    Passport::actingAs($stranger);
    $this->getJson("/api/workspaces/{$ws->id}/vault/invites/by-token/{$token}")->assertForbidden();
});
