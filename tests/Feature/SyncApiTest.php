<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ws = $this->user->ownedWorkspaces->first();
    Passport::actingAs($this->user);
});

it('pulls an empty workspace', function () {
    $this->getJson("/api/workspaces/{$this->ws->id}/changes?since=0")
        ->assertOk()
        ->assertExactJson(['cursor' => 0, 'vaultGeneration' => 0, 'vaultRotationPending' => false, 'vaultKeys' => ['mine' => [], 'recovery' => [], 'rotation' => []], 'folders' => [], 'notes' => []]);
});

it('pushes a note and pulls it back', function () {
    $id = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$this->ws->id}/changes", [
        'notes' => [['id' => $id, 'content' => 'hello', 'updatedAt' => now()->toIso8601String()]],
    ])->assertOk()
        ->assertJsonPath('cursor', 1)
        ->assertJsonPath('notes.0.content', 'hello');

    $this->getJson("/api/workspaces/{$this->ws->id}/changes?since=0")
        ->assertOk()
        ->assertJsonPath('cursor', 1)
        ->assertJsonPath('notes.0.id', $id);
});

it('rejects a malformed note (missing updatedAt)', function () {
    $this->postJson("/api/workspaces/{$this->ws->id}/changes", [
        'notes' => [['id' => Str::uuid()->toString(), 'content' => 'x']],
    ])->assertStatus(422);
});

it('forbids a non-member', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson("/api/workspaces/{$this->ws->id}/changes")->assertForbidden();
});

it('forbids a viewer from pushing', function () {
    $viewer = User::factory()->create();
    $this->ws->members()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer]);
    Passport::actingAs($viewer);

    $this->postJson("/api/workspaces/{$this->ws->id}/changes", ['notes' => []])->assertForbidden();
});
