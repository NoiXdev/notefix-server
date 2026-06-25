<?php

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a personal workspace when a user is created', function () {
    $user = User::factory()->create(['name' => 'Tom']);

    expect($user->ownedWorkspaces)->toHaveCount(1);

    $ws = $user->ownedWorkspaces->first();
    expect($ws->type)->toBe(WorkspaceType::Personal)
        ->and($ws->name)->toBe("Tom's Notes")
        ->and($ws->members)->toHaveCount(1);

    $member = $ws->members->first();
    expect($member->role)->toBe(WorkspaceRole::Owner)
        ->and($member->user_id)->toBe($user->id);
});

it('enforces unique membership per workspace and user', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();

    expect(fn () => $ws->members()->create([
        'user_id' => $user->id,
        'role' => WorkspaceRole::Editor,
    ]))->toThrow(QueryException::class);
});
