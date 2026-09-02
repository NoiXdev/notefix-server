<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

function vaultBody(): array
{
    return [
        'kdfParams' => ['salt' => 'AAAA', 'm' => 65536, 't' => 3, 'p' => 1],
        'dekWrapped' => 'wrap==', 'dekCheck' => 'check==',
        'recovery' => ['recoverySalt' => 'rs==', 'dekWrappedRecovery' => 'rwrap==', 'dekCheck' => 'check=='],
    ];
}

it('creates generation 1 once, then refuses with 409', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault", vaultBody())->assertCreated()->assertJsonPath('vaultGeneration', 1);
    $this->postJson("/api/workspaces/{$ws->id}/vault", vaultBody())->assertStatus(409);
    expect($ws->fresh()->vault_generation)->toBe(1)
        ->and($ws->vaultKeys()->where('user_id', $owner->id)->count())->toBe(1)
        ->and($ws->vaultRecovery()->count())->toBe(1);
});

it('forbids non-members and lets members replace their own key', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $member = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
    Passport::actingAs($stranger);
    $this->postJson("/api/workspaces/{$ws->id}/vault", vaultBody())->assertForbidden();

    Passport::actingAs($owner);
    $this->postJson("/api/workspaces/{$ws->id}/vault", vaultBody())->assertCreated();
    $this->putJson("/api/workspaces/{$ws->id}/vault/keys/me", ['generation' => 1, 'kdfParams' => ['salt' => 'BBBB'], 'dekWrapped' => 'wrap2==', 'dekCheck' => 'check=='])->assertOk();
    expect($ws->vaultKeys()->where('user_id', $owner->id)->first()->dek_wrapped)->toBe('wrap2==');

    Passport::actingAs($member); // holds no generation → cannot "replace"
    $this->putJson("/api/workspaces/{$ws->id}/vault/keys/me", ['generation' => 1, 'kdfParams' => ['salt' => 'C'], 'dekWrapped' => 'x', 'dekCheck' => 'c'])->assertNotFound();
});
