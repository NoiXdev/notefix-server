<?php

use App\Enums\WorkspaceRole;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->ws = $this->user->ownedWorkspaces->first();
    Passport::actingAs($this->user);
});

$path = 'ab/cd/ef/img.png';

it('uploads an image, stores the blob + manifest row, and lists it', function () use ($path) {
    $this->postJson("/api/workspaces/{$this->ws->id}/images", [
        'path' => $path,
        'file' => UploadedFile::fake()->image('img.png', 8, 8),
    ])->assertOk()->assertJsonPath('path', $path);

    Storage::disk('local')->assertExists("images/{$this->ws->id}/{$path}");
    expect(Image::where('workspace_id', $this->ws->id)->where('path', $path)->exists())->toBeTrue();

    $this->getJson("/api/workspaces/{$this->ws->id}/images")
        ->assertOk()
        ->assertJsonPath('data.0.path', $path);
});

it('downloads the bytes with an image content-type', function () use ($path) {
    $this->postJson("/api/workspaces/{$this->ws->id}/images", [
        'path' => $path,
        'file' => UploadedFile::fake()->image('img.png', 8, 8),
    ])->assertOk();

    $res = $this->get("/api/workspaces/{$this->ws->id}/image?path=".urlencode($path));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('image/png');
});

it('is idempotent on duplicate upload (single row)', function () use ($path) {
    $this->postJson("/api/workspaces/{$this->ws->id}/images", ['path' => $path, 'file' => UploadedFile::fake()->image('img.png', 8, 8)])->assertOk();
    $this->postJson("/api/workspaces/{$this->ws->id}/images", ['path' => $path, 'file' => UploadedFile::fake()->image('img.png', 8, 8)])->assertOk();
    expect(Image::where('workspace_id', $this->ws->id)->where('path', $path)->count())->toBe(1);
});

it('rejects a traversal path', function () {
    $this->postJson("/api/workspaces/{$this->ws->id}/images", [
        'path' => '../escape.png',
        'file' => UploadedFile::fake()->image('x.png'),
    ])->assertStatus(422);
});

it('rejects a non-image and an oversized file', function () use ($path) {
    $this->postJson("/api/workspaces/{$this->ws->id}/images", [
        'path' => $path, 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ])->assertStatus(422);
    $this->postJson("/api/workspaces/{$this->ws->id}/images", [
        'path' => $path, 'file' => UploadedFile::fake()->create('big.png', 11000, 'image/png'),
    ])->assertStatus(422);
});

it('forbids a non-member and a viewer upload', function () use ($path) {
    Passport::actingAs(User::factory()->create());
    $this->getJson("/api/workspaces/{$this->ws->id}/images")->assertForbidden();

    $viewer = User::factory()->create();
    $this->ws->members()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer]);
    Passport::actingAs($viewer);
    $this->postJson("/api/workspaces/{$this->ws->id}/images", [
        'path' => $path, 'file' => UploadedFile::fake()->image('x.png'),
    ])->assertForbidden();
});
