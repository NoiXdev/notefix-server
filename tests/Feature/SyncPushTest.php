<?php

use App\Models\Note;
use App\Models\Revision;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->sync = new SyncService;
    $this->actor = User::factory()->create();
    $this->ws = $this->actor->ownedWorkspaces->first();
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function noteInput(string $id, string $content, string $updatedAt, array $extra = []): array
{
    return array_merge(['id' => $id, 'content' => $content, 'updatedAt' => $updatedAt], $extra);
}

it('creates a new note and advances the cursor', function () {
    $id = Str::uuid()->toString();

    $result = $this->sync->push($this->ws, $this->actor, [], [noteInput($id, 'hi', now()->toIso8601String())]);

    expect(Note::find($id)?->content)->toBe('hi')
        ->and(Note::find($id)?->sync_seq)->toBe(1)
        ->and($result['cursor'])->toBe(1);
});

it('overwrites with a newer incoming version and keeps a revision', function () {
    $id = Str::uuid()->toString();
    Note::create(['id' => $id, 'workspace_id' => $this->ws->id, 'content' => 'old', 'sync_seq' => 1, 'updated_at' => now()->subHour()]);

    $this->sync->push($this->ws, $this->actor, [], [noteInput($id, 'new', now()->toIso8601String())]);

    $rev = Revision::where('note_id', $id)->first();
    expect(Note::find($id)->content)->toBe('new')
        ->and(Revision::where('note_id', $id)->count())->toBe(1)
        ->and($rev->content)->toBe('old')
        ->and($rev->author_id)->toBe($this->actor->id);
});

it('ignores an older incoming version so the server wins', function () {
    $id = Str::uuid()->toString();
    Note::create(['id' => $id, 'workspace_id' => $this->ws->id, 'content' => 'server', 'sync_seq' => 1, 'updated_at' => now()]);

    $this->sync->push($this->ws, $this->actor, [], [noteInput($id, 'stale', now()->subHour()->toIso8601String())]);

    expect(Note::find($id)->content)->toBe('server')
        ->and(Revision::where('note_id', $id)->count())->toBe(0);
});

it('tombstones a note on delete', function () {
    $id = Str::uuid()->toString();
    Note::create(['id' => $id, 'workspace_id' => $this->ws->id, 'content' => 'x', 'sync_seq' => 1, 'updated_at' => now()->subHour()]);

    $this->sync->push($this->ws, $this->actor, [], [
        noteInput($id, 'x', now()->toIso8601String(), ['deletedAt' => now()->toIso8601String()]),
    ]);

    expect(Note::find($id))->toBeNull()
        ->and(Note::withTrashed()->find($id)->trashed())->toBeTrue();
});
