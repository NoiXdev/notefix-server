<?php

namespace App\Http\Controllers;

use App\Http\Resources\FolderResource;
use App\Http\Resources\NoteResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SyncController extends Controller
{
    public function __construct(private SyncService $sync) {}

    /**
     * @return array<string, mixed>
     */
    public function pull(Request $request, Workspace $workspace): array
    {
        Gate::authorize('view', $workspace);

        $result = $this->sync->pull($workspace, $request->integer('since'));

        return [
            'cursor' => $result['cursor'],
            'folders' => FolderResource::collection($result['folders']),
            'notes' => NoteResource::collection($result['notes']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function push(Request $request, Workspace $workspace): array
    {
        Gate::authorize('update', $workspace);

        // Validate the structure, but push the full payload — validate() would
        // strip every field that has no rule (content, pinned, …).
        $request->validate([
            'folders' => ['sometimes', 'array'],
            'folders.*.id' => ['required', 'uuid'],
            'folders.*.updatedAt' => ['required', 'date'],
            'notes' => ['sometimes', 'array'],
            'notes.*.id' => ['required', 'uuid'],
            'notes.*.updatedAt' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->sync->push(
            $workspace,
            $user,
            $request->input('folders', []),
            $request->input('notes', []),
        );

        return [
            'cursor' => $result['cursor'],
            'folders' => FolderResource::collection($result['folders']),
            'notes' => NoteResource::collection($result['notes']),
        ];
    }
}
