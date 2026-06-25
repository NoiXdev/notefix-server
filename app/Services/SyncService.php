<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Support\Collection;

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
}
