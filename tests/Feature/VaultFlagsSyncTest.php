<?php

use App\Models\Note;
use App\Models\Revision;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('round-trips protected, title and locked through push and pull', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();
    $fid = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'Secret', 'locked' => true, 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
        'notes' => [['id' => $nid, 'content' => 'b64ciphertext==', 'protected' => true, 'title' => 'Visible title', 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk()
        ->assertJsonPath('notes.0.protected', true)
        ->assertJsonPath('notes.0.title', 'Visible title')
        ->assertJsonPath('folders.0.locked', true);

    $pulled = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json();
    $note = collect($pulled['notes'])->firstWhere('id', $nid);
    $folder = collect($pulled['folders'])->firstWhere('id', $fid);
    expect($note['protected'])->toBeTrue()
        ->and($note['title'])->toBe('Visible title')
        ->and($note['content'])->toBe('b64ciphertext==')
        ->and($folder['locked'])->toBeTrue();
});

it('defaults the flags when a client omits them', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();
    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => '<p>hi</p>', 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('notes.0.protected', false)->assertJsonPath('notes.0.title', '');
});

it('keeps protected, title and keyGen on a re-push that omits them, and records no revision', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => 'b64ciphertext==', 'protected' => true, 'title' => 'Visible title', 'keyGen' => 3, 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk();

    // A pre-feature client re-pushes the note without ever having heard of
    // protected/title/keyGen — absent must mean "keep", not "clear".
    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => 'b64ciphertext==', 'updatedAt' => now()->addMinute()->toIso8601String(), 'position' => 0]],
    ])->assertOk()
        ->assertJsonPath('notes.0.protected', true)
        ->assertJsonPath('notes.0.title', 'Visible title')
        ->assertJsonPath('notes.0.keyGen', 3);

    $note = Note::find($nid);
    expect($note->protected)->toBeTrue()
        ->and($note->title)->toBe('Visible title')
        ->and($note->key_gen)->toBe(3)
        ->and(Revision::where('note_id', $nid)->count())->toBe(0);
});

it('still unprotects and clears keyGen on an explicit protected:false', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => 'b64ciphertext==', 'protected' => true, 'title' => 'Visible title', 'keyGen' => 3, 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => 'plaintext', 'protected' => false, 'updatedAt' => now()->addMinute()->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('notes.0.protected', false);

    $note = Note::find($nid);
    expect($note->protected)->toBeFalse()
        ->and($note->key_gen)->toBeNull();
});

it('keeps a folder locked on a re-push that omits the flag, but an explicit false unlocks it', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $fid = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'Secret', 'locked' => true, 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'Secret', 'updatedAt' => now()->addMinute()->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('folders.0.locked', true);

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'Secret', 'locked' => false, 'updatedAt' => now()->addMinutes(2)->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('folders.0.locked', false);
});
