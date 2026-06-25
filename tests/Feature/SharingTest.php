<?php

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\User;

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
