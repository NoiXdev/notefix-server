<?php

namespace App\Http\Controllers;

use App\Http\Resources\FolderResource;
use App\Http\Resources\NoteResource;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVaultInvite;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use App\Models\WorkspaceVaultRotationWrap;
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
        $workspace->refresh();

        /** @var User $user */
        $user = $request->user();

        return [
            'cursor' => $result['cursor'],
            'vaultGeneration' => (int) $workspace->vault_generation,
            'vaultRotationPending' => (bool) $workspace->vault_rotation_pending,
            'vaultKeys' => $this->vaultKeysFor($workspace, $user),
            'workspaceRole' => $workspace->memberRole($user)->value,
            'vaultInvites' => $this->vaultInvitesFor($workspace, $user),
            'folders' => FolderResource::collection($result['folders']),
            'notes' => NoteResource::collection($result['notes']),
        ];
    }

    /** @return array{mine: list<array<string,mixed>>, recovery: list<array<string,mixed>>, rotation: list<array<string,mixed>>} */
    private function vaultKeysFor(Workspace $workspace, User $user): array
    {
        $mine = $workspace->vaultKeys()->where('user_id', $user->id)->orderBy('generation')->get()
            ->map(fn (WorkspaceVaultKey $k) => [
                'generation' => $k->generation, 'kdfParams' => $k->kdf_params,
                'dekWrapped' => $k->dek_wrapped, 'dekCheck' => $k->dek_check,
            ])->values()->all();
        $recovery = $workspace->vaultRecovery()->where('created_by', $user->id)->orderBy('generation')->get()
            ->map(fn (WorkspaceVaultRecovery $r) => [
                'generation' => $r->generation, 'recoverySalt' => $r->recovery_salt,
                'dekWrappedRecovery' => $r->dek_wrapped_recovery, 'dekCheck' => $r->dek_check,
            ])->values()->all();
        $rotation = $workspace->vaultRotationWraps()->where('user_id', $user->id)->orderBy('generation')->get()
            ->map(fn (WorkspaceVaultRotationWrap $w) => [
                'generation' => $w->generation, 'kdfParams' => $w->kdf_params,
                'dekWrapped' => $w->dek_wrapped, 'dekCheck' => $w->dek_check,
            ])->values()->all();

        return ['mine' => $mine, 'recovery' => $recovery, 'rotation' => $rotation];
    }

    /**
     * An owner's open invitations with the generation of the vault wrap
     * attached to each (null when none) — so the owner's client can tell
     * which invitations lost their code in a rotation. Empty for non-owners.
     *
     * @return list<array{invitationId: int, generation: int|null}>
     */
    private function vaultInvitesFor(Workspace $workspace, User $user): array
    {
        if (! Gate::forUser($user)->allows('manage', $workspace)) {
            return [];
        }
        $open = $workspace->invitations()
            ->whereNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('id')->get();
        $wraps = WorkspaceVaultInvite::whereIn('invitation_id', $open->pluck('id'))->pluck('generation', 'invitation_id');

        return $open->map(fn (Invitation $i) => [
            'invitationId' => (int) $i->id,
            'generation' => isset($wraps[$i->id]) ? (int) $wraps[$i->id] : null,
        ])->values()->all();
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
        $workspace->refresh();

        return [
            'cursor' => $result['cursor'],
            'vaultGeneration' => (int) $workspace->vault_generation,
            'vaultRotationPending' => (bool) $workspace->vault_rotation_pending,
            'folders' => FolderResource::collection($result['folders']),
            'notes' => NoteResource::collection($result['notes']),
        ];
    }
}
