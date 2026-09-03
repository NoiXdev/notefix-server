<?php

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('marks an invitation open until accepted or expired', function () {
    $ws = User::factory()->create()->ownedWorkspaces->first();
    $owner = $ws->owner;

    $open = Invitation::create(['workspace_id' => $ws->id, 'token' => 'tok-open', 'role' => WorkspaceRole::Editor, 'invited_by' => $owner->id, 'created_at' => now()]);
    expect($open->isOpen())->toBeTrue();

    $expired = Invitation::create(['workspace_id' => $ws->id, 'token' => 'tok-exp', 'role' => WorkspaceRole::Editor, 'invited_by' => $owner->id, 'expires_at' => now()->subDay(), 'created_at' => now()]);
    expect($expired->isOpen())->toBeFalse();

    $used = Invitation::create(['workspace_id' => $ws->id, 'token' => 'tok-used', 'role' => WorkspaceRole::Editor, 'invited_by' => $owner->id, 'accepted_at' => now(), 'created_at' => now()]);
    expect($used->isOpen())->toBeFalse();
});

it('lets only an owner manage a workspace', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $editor = User::factory()->create();
    $ws->members()->create(['user_id' => $editor->id, 'role' => WorkspaceRole::Editor]);
    $stranger = User::factory()->create();

    expect($owner->can('manage', $ws))->toBeTrue()
        ->and($editor->can('manage', $ws))->toBeFalse()
        ->and($stranger->can('manage', $ws))->toBeFalse();
});

it('owner creates an invite; non-owner is forbidden', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();

    $this->actingAs($owner)->post("/workspaces/{$ws->id}/invitations", ['role' => 'editor'])->assertRedirect();
    expect($ws->invitations()->count())->toBe(1)
        ->and($ws->invitations()->first()->role)->toBe(WorkspaceRole::Editor);

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->post("/workspaces/{$ws->id}/invitations", ['role' => 'editor'])->assertForbidden();
});

it('owner changes a member role and removes a member', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $member = User::factory()->create();
    $row = $ws->members()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Viewer]);

    $this->actingAs($owner)->patch("/workspaces/{$ws->id}/members/{$row->id}", ['role' => 'editor'])->assertRedirect();
    expect($row->fresh()->role)->toBe(WorkspaceRole::Editor);

    $this->actingAs($owner)->delete("/workspaces/{$ws->id}/members/{$row->id}")->assertRedirect();
    expect(WorkspaceMember::find($row->id))->toBeNull();
});

it('protects the last owner from removal and demotion', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $ownerRow = $ws->members()->where('user_id', $owner->id)->first();

    $this->actingAs($owner)->delete("/workspaces/{$ws->id}/members/{$ownerRow->id}")->assertSessionHas('error');
    expect(WorkspaceMember::find($ownerRow->id))->not->toBeNull();

    $this->actingAs($owner)->patch("/workspaces/{$ws->id}/members/{$ownerRow->id}", ['role' => 'editor'])->assertSessionHas('error');
    expect($ownerRow->fresh()->role)->toBe(WorkspaceRole::Owner);
});

it('renders the share page for the owner and forbids others', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $this->actingAs($owner)->get("/workspaces/{$ws->id}/share")->assertOk();
    $this->actingAs(User::factory()->create())->get("/workspaces/{$ws->id}/share")->assertForbidden();
});

it('lists only open invitations on the share page', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $mk = fn (array $a) => Invitation::create(array_merge(
        ['workspace_id' => $ws->id, 'token' => Str::random(40), 'role' => 'editor', 'invited_by' => $owner->id, 'created_at' => now()],
        $a,
    ));
    $open = $mk(['expires_at' => now()->addDay()]);
    $never = $mk([]);
    $mk(['expires_at' => now()->subDay()]);
    $mk(['accepted_at' => now(), 'accepted_by' => $owner->id]);

    $this->actingAs($owner)->get("/workspaces/{$ws->id}/share")->assertOk()
        ->assertViewHas('invitations', fn ($list) => $list->pluck('id')->sort()->values()->all() === collect([$open->id, $never->id])->sort()->values()->all());
});

it('accepts an open invite, creating a membership and consuming it', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $invite = Invitation::create(['workspace_id' => $ws->id, 'token' => 'join-me', 'role' => WorkspaceRole::Editor, 'invited_by' => $owner->id, 'created_at' => now()]);

    $invitee = User::factory()->create();
    $this->actingAs($invitee)->post('/invite/join-me/accept')->assertRedirect('/workspaces');

    expect($ws->memberRole($invitee))->toBe(WorkspaceRole::Editor)
        ->and($invite->fresh()->isOpen())->toBeFalse();
});

it('shows a friendly error for a used token', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    Invitation::create(['workspace_id' => $ws->id, 'token' => 'once', 'role' => WorkspaceRole::Viewer, 'invited_by' => $owner->id, 'accepted_at' => now(), 'created_at' => now()]);

    $this->actingAs(User::factory()->create())->get('/invite/once')->assertOk()->assertSee('no longer', false);
});

it('redirects a guest to login', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    Invitation::create(['workspace_id' => $ws->id, 'token' => 'guesttok', 'role' => WorkspaceRole::Viewer, 'invited_by' => $owner->id, 'created_at' => now()]);

    $this->get('/invite/guesttok')->assertRedirect('/login');
});
