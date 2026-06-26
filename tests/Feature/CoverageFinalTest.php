<?php

use App\Enums\WorkspaceRole;
use App\Mail\Transport\PostalTransport;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

it('shows an open invitation and lets a new user accept it', function () {
    $ws = User::factory()->create()->ownedWorkspaces->first();
    Invitation::create([
        'workspace_id' => $ws->id, 'token' => 'tok-open', 'role' => WorkspaceRole::Editor,
        'invited_by' => $ws->owner->id, 'created_at' => now(),
    ]);
    $joiner = User::factory()->create();

    actingAs($joiner);
    $this->get('/invite/tok-open')->assertOk()->assertViewIs('invite.show');
    $this->post('/invite/tok-open/accept')->assertRedirect('/workspaces');

    expect(WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $joiner->id)->exists())->toBeTrue();
});

it('redirects the setup wizard to the admin panel once setup is complete', function () {
    $this->get('/setup')->assertRedirect('/admin');
    $this->post('/setup', [])->assertRedirect('/admin');
});

it('stringifies the postal transport with its host', function () {
    expect((string) new PostalTransport('https://postal.example.test', 'k'))
        ->toBe('postal://postal.example.test');
});

it('serves images with the matching content type per extension', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);

    foreach (['jpg' => 'image/jpeg', 'gif' => 'image/gif'] as $ext => $mime) {
        $path = "a/pic.{$ext}";
        $this->postJson("/api/workspaces/{$ws->id}/images", [
            'path' => $path,
            'file' => UploadedFile::fake()->image("pic.{$ext}", 8, 8),
        ])->assertOk();

        $res = $this->get("/api/workspaces/{$ws->id}/image?path=".urlencode($path));
        $res->assertOk();
        expect($res->headers->get('content-type'))->toContain($mime);
    }
});
