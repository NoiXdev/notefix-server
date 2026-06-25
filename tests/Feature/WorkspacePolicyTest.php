<?php

use App\Enums\WorkspaceRole;
use App\Models\User;

it('lets a member view but denies a non-member', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $stranger = User::factory()->create();

    expect($owner->can('view', $ws))->toBeTrue()
        ->and($stranger->can('view', $ws))->toBeFalse();
});

it('lets owner and editor update but denies viewer', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();

    $viewer = User::factory()->create();
    $ws->members()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer]);

    $editor = User::factory()->create();
    $ws->members()->create(['user_id' => $editor->id, 'role' => WorkspaceRole::Editor]);

    expect($owner->can('update', $ws))->toBeTrue()
        ->and($editor->can('update', $ws))->toBeTrue()
        ->and($viewer->can('update', $ws))->toBeFalse()
        ->and($viewer->can('view', $ws))->toBeTrue();
});
