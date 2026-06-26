<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Http\Resources\FolderResource;
use App\Mail\TestMail;
use App\Models\Folder;
use App\Models\Image;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('labels workspace roles and types', function () {
    expect(WorkspaceRole::Owner->getLabel())->toBe('Owner')
        ->and(WorkspaceRole::Editor->getLabel())->toBe('Editor')
        ->and(WorkspaceRole::Viewer->getLabel())->toBe('Viewer')
        ->and(WorkspaceType::Personal->getLabel())->toBe('Personal')
        ->and(WorkspaceType::Shared->getLabel())->toBe('Shared');
});

it('serializes a folder resource', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    $folder = Folder::forceCreate([
        'id' => 'fold-1', 'workspace_id' => $ws->id, 'parent_id' => null,
        'name' => 'Docs', 'icon' => 'folder', 'color' => '#fff', 'sort' => 'name',
        'position' => 2, 'sync_seq' => 1,
    ]);

    $array = (new FolderResource($folder))->toArray(request());

    expect($array['id'])->toBe('fold-1')
        ->and($array['name'])->toBe('Docs')
        ->and($array['position'])->toBe(2)
        ->and($array)->toHaveKeys(['parentId', 'icon', 'color', 'sort', 'updatedAt', 'deletedAt']);
});

it('relates images and notes to their workspace', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();

    $image = Image::create(['workspace_id' => $ws->id, 'path' => 'a/x.png', 'size' => 3, 'hash' => 'h', 'created_at' => now()]);
    expect($image->workspace->id)->toBe($ws->id);

    $note = Note::forceCreate(['id' => 'n1', 'workspace_id' => $ws->id, 'content' => '{}', 'updated_at' => now(), 'sync_seq' => 1]);
    expect($note->workspace->id)->toBe($ws->id)
        ->and($note->revisions()->count())->toBe(0);
});

it('builds the test mail content', function () {
    $built = (new TestMail)->content();
    expect($built->htmlString)->toContain('configuration works');
});

it('updates a user profile and password via the Fortify actions', function (): void {
    $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.test', 'password' => Hash::make('secret-pw')]);

    (new UpdateUserProfileInformation)->update($user, ['name' => 'New', 'email' => 'new@example.test']);
    expect($user->fresh()->name)->toBe('New')->and($user->fresh()->email)->toBe('new@example.test');

    actingAs($user); // current_password:web checks the authenticated user
    (new UpdateUserPassword)->update($user, ['current_password' => 'secret-pw', 'password' => 'brand-new-pw', 'password_confirmation' => 'brand-new-pw']);
    expect(Hash::check('brand-new-pw', $user->fresh()->password))->toBeTrue();

    (new ResetUserPassword)->reset($user, ['password' => 'reset-pw-123', 'password_confirmation' => 'reset-pw-123']);
    expect(Hash::check('reset-pw-123', $user->fresh()->password))->toBeTrue();
});
