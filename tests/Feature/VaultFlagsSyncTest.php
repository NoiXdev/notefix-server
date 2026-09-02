<?php

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
