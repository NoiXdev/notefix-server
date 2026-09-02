<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('carries keyGen on notes and the vault generation on the pull envelope', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();
    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => 'cipher==', 'protected' => true, 'keyGen' => 2, 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('notes.0.keyGen', 2);

    $body = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json();
    expect(collect($body['notes'])->firstWhere('id', $nid)['keyGen'])->toBe(2)
        ->and($body['vaultGeneration'])->toBe(0)
        ->and($body['vaultRotationPending'])->toBeFalse();
});

it('stores a null keyGen for unprotected notes', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();
    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'notes' => [['id' => $nid, 'content' => '<p>x</p>', 'keyGen' => 5, 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('notes.0.keyGen', null);
});
