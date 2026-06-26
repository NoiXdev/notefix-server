<?php

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Revision;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Support\ImagePath;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('rejects empty and absolute image paths', function () {
    expect(ImagePath::isSafe(''))->toBeFalse()
        ->and(ImagePath::isSafe('/abs/x.png'))->toBeFalse()
        ->and(ImagePath::isSafe('ok/x.png'))->toBeTrue();
});

it('exposes the revision and member relations', function () {
    expect((new Revision)->note())->toBeInstanceOf(BelongsTo::class)
        ->and((new WorkspaceMember)->workspace())->toBeInstanceOf(BelongsTo::class);
});

it('keeps the server folder when an older copy is pushed', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $fid = Str::uuid()->toString();

    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'Current', 'updatedAt' => now()->toIso8601String(), 'position' => 0]],
    ])->assertOk()->assertJsonPath('folders.0.name', 'Current');

    // Older copy → server keeps the newer version.
    $this->postJson("/api/workspaces/{$ws->id}/changes", [
        'folders' => [['id' => $fid, 'name' => 'Stale', 'updatedAt' => now()->subDay()->toIso8601String(), 'position' => 0]],
    ])->assertOk();

    $pulled = $this->getJson("/api/workspaces/{$ws->id}/changes?since=0")->assertOk()->json('folders');
    expect(collect($pulled)->firstWhere('id', $fid)['name'])->toBe('Current');
});

it('shows an error for a closed invitation and blocks acceptance', function () {
    $ws = User::factory()->create()->ownedWorkspaces->first();
    Invitation::create([
        'workspace_id' => $ws->id, 'token' => 'tok-closed', 'role' => WorkspaceRole::Editor,
        'invited_by' => $ws->owner->id, 'accepted_at' => now(), 'created_at' => now(),
    ]);

    actingAs(User::factory()->create());
    $this->get('/invite/tok-closed')->assertOk()->assertViewIs('invite.error');
    $this->post('/invite/tok-closed/accept')->assertRedirect('/workspaces');
});

it('lists workspaces and lets an owner revoke an invitation', function () {
    $owner = User::factory()->create();
    $ws = $owner->ownedWorkspaces->first();
    $inv = Invitation::create([
        'workspace_id' => $ws->id, 'token' => 'tok-rev', 'role' => WorkspaceRole::Editor,
        'invited_by' => $owner->id, 'created_at' => now(),
    ]);

    actingAs($owner);
    $this->get('/workspaces')->assertOk();
    $this->delete("/workspaces/{$ws->id}/invitations/{$inv->id}")->assertRedirect();
    expect(Invitation::find($inv->id))->toBeNull();
});
