<?php

use App\Models\Folder;
use App\Models\Note;
use App\Models\User;

it('creates and soft-deletes a note with a client-provided uuid', function () {
    $ws = User::factory()->create()->ownedWorkspaces->first();

    $note = Note::create([
        'id' => '11111111-1111-1111-1111-111111111111',
        'workspace_id' => $ws->id,
        'content' => 'hello',
        'sync_seq' => 1,
        'updated_at' => now(),
    ]);

    expect($note->id)->toBe('11111111-1111-1111-1111-111111111111');

    $note->delete();

    expect(Note::find($note->id))->toBeNull()
        ->and(Note::withTrashed()->find($note->id))->not->toBeNull();
});

it('creates a folder under a workspace', function () {
    $ws = User::factory()->create()->ownedWorkspaces->first();

    $folder = Folder::create([
        'id' => '22222222-2222-2222-2222-222222222222',
        'workspace_id' => $ws->id,
        'name' => 'Work',
        'sync_seq' => 1,
        'updated_at' => now(),
    ]);

    expect($ws->folders()->count())->toBe(1)
        ->and($folder->workspace->id)->toBe($ws->id);
});
