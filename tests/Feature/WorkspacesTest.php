<?php

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use Laravel\Passport\Passport;

it('lists the authenticated user\'s workspaces with their role', function () {
    $user = User::factory()->create();
    // The personal workspace is auto-created by the UserObserver (owner member).
    $owned = $user->ownedWorkspaces()->first();

    // A shared workspace owned by someone else, where $user is an editor.
    $owner = User::factory()->create();
    $shared = $owner->ownedWorkspaces()->create(['name' => 'Team', 'type' => WorkspaceType::Shared]);
    $shared->members()->create(['user_id' => $user->id, 'role' => WorkspaceRole::Editor]);

    Passport::actingAs($user);

    $res = $this->getJson('/api/workspaces')->assertOk();

    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($owned->id)->toContain($shared->id);

    $sharedRow = collect($res->json('data'))->firstWhere('id', $shared->id);
    expect($sharedRow['role'])->toBe('editor')
        ->and($sharedRow['name'])->toBe('Team');
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/workspaces')->assertUnauthorized();
});
