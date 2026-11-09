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
        ->assertExactJson(['cursor' => 0, 'vaultGeneration' => 0, 'vaultRotationPending' => false, 'vaultKeys' => ['mine' => [], 'recovery' => [], 'rotation' => []], 'workspaceRole' => 'owner', 'vaultInvites' => [], 'folders' => [], 'notes' => []]);
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

it('keeps millisecond timestamps through a push and pull', function () {
    $nid = Str::uuid()->toString();
    $fid = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$this->ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'F', 'updatedAt' => '2026-11-09T08:15:05.282Z', 'deletedAt' => '2026-11-09T08:15:06.004Z']],
        'notes' => [['id' => $nid, 'content' => 'x', 'updatedAt' => '2026-11-09T08:15:05.282Z', 'dueAt' => '2026-11-10T09:00:00.120Z']],
    ])->assertOk()
        ->assertJsonPath('notes.0.updatedAt', '2026-11-09T08:15:05.282+00:00');

    $this->getJson("/api/workspaces/{$this->ws->id}/changes?since=0")
        ->assertOk()
        ->assertJsonPath('notes.0.updatedAt', '2026-11-09T08:15:05.282+00:00')
        ->assertJsonPath('notes.0.dueAt', '2026-11-10T09:00:00.120+00:00')
        ->assertJsonPath('folders.0.deletedAt', '2026-11-09T08:15:06.004+00:00');
});

it('decides last-write-wins within the same second', function () {
    $id = Str::uuid()->toString();
    $push = fn (string $content, string $at) => $this->postJson("/api/workspaces/{$this->ws->id}/changes", [
        'notes' => [['id' => $id, 'content' => $content, 'updatedAt' => $at]],
    ])->assertOk();

    $push('newer', '2026-11-09T08:15:05.900Z');
    $push('older', '2026-11-09T08:15:05.300Z'); // same second, earlier → rejected

    $this->getJson("/api/workspaces/{$this->ws->id}/changes?since=0")
        ->assertJsonPath('notes.0.content', 'newer');
});
