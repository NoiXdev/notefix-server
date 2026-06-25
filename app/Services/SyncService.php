<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Note;
use App\Models\Revision;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncService
{
    /**
     * Hand out the next monotonic sequence number for a workspace. The row is
     * locked so concurrent pushes within a transaction get distinct values.
     */
    public function allocateSeq(Workspace $workspace): int
    {
        $locked = Workspace::whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();
        $next = (int) $locked->sync_cursor + 1;
        $locked->sync_cursor = $next;
        $locked->save();

        $workspace->sync_cursor = $next;

        return $next;
    }

    /**
     * Everything in the workspace changed after `$since` (tombstones included),
     * ordered by sequence, plus the new cursor.
     *
     * @return array{cursor: int, folders: Collection<int, Folder>, notes: Collection<int, Note>}
     */
    public function pull(Workspace $workspace, int $since): array
    {
        $folders = $workspace->folders()->withTrashed()
            ->where('sync_seq', '>', $since)->orderBy('sync_seq')->get();

        $notes = $workspace->notes()->withTrashed()
            ->where('sync_seq', '>', $since)->orderBy('sync_seq')->get();

        $cursor = max($since, (int) $folders->max('sync_seq'), (int) $notes->max('sync_seq'));

        return ['cursor' => $cursor, 'folders' => $folders, 'notes' => $notes];
    }

    /**
     * Apply a batch of client changes. Server wins on a newer stored version
     * (last-write-wins by updatedAt); an overwritten note's previous content is
     * kept as a revision. Returns the canonical applied entities + cursor.
     *
     * @param  array<int, array<string, mixed>>  $folders
     * @param  array<int, array<string, mixed>>  $notes
     * @return array{cursor: int, folders: Collection<int, Folder>, notes: Collection<int, Note>}
     */
    public function push(Workspace $workspace, User $actor, array $folders, array $notes): array
    {
        return DB::transaction(function () use ($workspace, $actor, $folders, $notes) {
            $appliedFolders = collect($folders)
                ->map(fn (array $in) => $this->applyFolder($workspace, $in))
                ->filter()
                ->values();

            $appliedNotes = collect($notes)
                ->map(fn (array $in) => $this->applyNote($workspace, $actor, $in))
                ->filter()
                ->values();

            return [
                'cursor' => (int) $workspace->sync_cursor,
                'folders' => $appliedFolders,
                'notes' => $appliedNotes,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $in
     */
    private function applyFolder(Workspace $workspace, array $in): ?Folder
    {
        $updatedAt = Carbon::parse($in['updatedAt']);
        $folder = Folder::withTrashed()->where('workspace_id', $workspace->id)->find($in['id']);

        if ($folder && $folder->updated_at?->greaterThan($updatedAt)) {
            return null; // server version is newer → keep it
        }

        $folder ??= new Folder(['id' => $in['id'], 'workspace_id' => $workspace->id]);

        $folder->forceFill([
            'parent_id' => $in['parentId'] ?? null,
            'name' => $in['name'] ?? $folder->name ?? '',
            'icon' => $in['icon'] ?? null,
            'color' => $in['color'] ?? null,
            'sort' => $in['sort'] ?? null,
            'position' => $in['position'] ?? 0,
            'updated_at' => $updatedAt,
            'deleted_at' => empty($in['deletedAt']) ? null : Carbon::parse($in['deletedAt']),
            'sync_seq' => $this->allocateSeq($workspace),
        ]);
        $folder->save();

        return $folder;
    }

    /**
     * @param  array<string, mixed>  $in
     */
    private function applyNote(Workspace $workspace, User $actor, array $in): ?Note
    {
        $updatedAt = Carbon::parse($in['updatedAt']);
        $note = Note::withTrashed()->where('workspace_id', $workspace->id)->find($in['id']);

        if ($note && $note->updated_at?->greaterThan($updatedAt)) {
            return null;
        }

        // Keep the overwritten content in history when an existing note changes.
        if ($note && array_key_exists('content', $in) && $note->content !== $in['content']) {
            Revision::create([
                'note_id' => $note->id,
                'content' => $note->content,
                'author_id' => $actor->id,
                'created_at' => now(),
            ]);
        }

        $note ??= new Note(['id' => $in['id'], 'workspace_id' => $workspace->id]);

        $note->forceFill([
            'folder_id' => $in['folderId'] ?? null,
            'content' => $in['content'] ?? $note->content ?? '',
            'pinned' => $in['pinned'] ?? false,
            'archived' => $in['archived'] ?? false,
            'color' => $in['color'] ?? null,
            'due_at' => empty($in['dueAt']) ? null : Carbon::parse($in['dueAt']),
            'position' => $in['position'] ?? 0,
            'updated_at' => $updatedAt,
            'updated_by' => $actor->id,
            'deleted_at' => empty($in['deletedAt']) ? null : Carbon::parse($in['deletedAt']),
            'sync_seq' => $this->allocateSeq($workspace),
        ]);
        $note->save();

        return $note;
    }
}
