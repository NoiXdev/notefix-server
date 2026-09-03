<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInviteWrapRequest;
use App\Http\Requests\AttachInviteWrapRequest;
use App\Http\Requests\CreateVaultRequest;
use App\Http\Requests\PutMyVaultKeyRequest;
use App\Http\Requests\PutRecoveryWrapRequest;
use App\Http\Requests\RotateVaultRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVaultInvite;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use App\Models\WorkspaceVaultRotationWrap;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VaultKeyController extends Controller
{
    public static function authorizeMember(Workspace $workspace, User $user): void
    {
        abort_unless($workspace->memberRole($user) !== null, 403);
    }

    public function create(CreateVaultRequest $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('manage', $workspace);
        $d = $request->validated();

        return DB::transaction(function () use ($workspace, $user, $d) {
            $locked = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->vault_generation > 0, 409, 'vault already exists');
            WorkspaceVaultKey::create([
                'workspace_id' => $locked->id, 'user_id' => $user->id, 'generation' => 1,
                'kdf_params' => $d['kdfParams'], 'dek_wrapped' => $d['dekWrapped'], 'dek_check' => $d['dekCheck'],
            ]);
            WorkspaceVaultRecovery::create([
                'workspace_id' => $locked->id, 'generation' => 1, 'created_by' => $user->id,
                'recovery_salt' => $d['recovery']['recoverySalt'],
                'dek_wrapped_recovery' => $d['recovery']['dekWrappedRecovery'],
                'dek_check' => $d['recovery']['dekCheck'],
            ]);
            $locked->forceFill(['vault_generation' => 1])->save();

            return response()->json(['vaultGeneration' => 1], 201);
        });
    }

    public function putMine(PutMyVaultKeyRequest $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);
        $d = $request->validated();
        $key = WorkspaceVaultKey::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)->where('generation', $d['generation'])->first();

        if ($key !== null) {
            $key->forceFill(['kdf_params' => $d['kdfParams'], 'dek_wrapped' => $d['dekWrapped'], 'dek_check' => $d['dekCheck']])->save();

            return response()->json(['ok' => true]);
        }

        $wrap = WorkspaceVaultRotationWrap::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)->where('generation', $d['generation'])->first();
        abort_if($wrap === null, 404, 'no key for that generation');

        DB::transaction(function () use ($workspace, $user, $d, $wrap) {
            WorkspaceVaultKey::create([
                'workspace_id' => $workspace->id, 'user_id' => $user->id, 'generation' => $d['generation'],
                'kdf_params' => $d['kdfParams'], 'dek_wrapped' => $d['dekWrapped'], 'dek_check' => $d['dekCheck'],
            ]);
            $wrap->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function attachInvite(AttachInviteWrapRequest $request, Workspace $workspace, Invitation $invitation): JsonResponse
    {
        Gate::authorize('manage', $workspace);
        abort_unless($invitation->workspace_id === $workspace->id, 404);
        abort_unless($invitation->isOpen(), 410, 'invitation is not open');
        $d = $request->validated();
        abort_unless((int) $d['generation'] === (int) $workspace->vault_generation, 422, 'generation mismatch');

        DB::transaction(function () use ($workspace, $invitation, $d) {
            // Lock the invitation row so two concurrent attaches serialize:
            // the second one re-reads $existing after the first commits and
            // gets a clean 409, instead of both racing into the unique index.
            Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $existing = WorkspaceVaultInvite::where('invitation_id', $invitation->id)->first();
            // A wrap still at the current generation is a genuine duplicate → 409.
            // A wrap left over from before a rotation is stale and safe to replace.
            abort_if($existing !== null && $existing->generation >= $workspace->vault_generation, 409, 'wrap already attached');
            $existing?->delete();
            WorkspaceVaultInvite::create([
                'invitation_id' => $invitation->id, 'generation' => $d['generation'],
                'kdf_params' => $d['kdfParams'], 'dek_wrapped_invite' => $d['dekWrapped'], 'dek_check' => $d['dekCheck'],
            ]);
        });

        return response()->json(['ok' => true], 201);
    }

    private function inviteWrapFor(Workspace $workspace, Invitation $invitation, User $user): WorkspaceVaultInvite
    {
        abort_unless($invitation->workspace_id === $workspace->id && $invitation->accepted_by === $user->id, 404);
        $wrap = WorkspaceVaultInvite::where('invitation_id', $invitation->id)->first();
        abort_if($wrap === null, 404);

        return $wrap;
    }

    /**
     * Map an invitation token — the only part of an invitation a user ever
     * sees, since the share link is `/invite/{token}` — to the numeric id the
     * other vault-invite endpoints are keyed by.
     *
     * Deliberately open only to the two parties who already hold the token:
     * the workspace owner (who created the link) and the invitee who accepted
     * it. Any other member gets a 404 rather than a 403, so this cannot be
     * used to probe which tokens exist.
     */
    public function resolveInvite(Request $request, Workspace $workspace, string $token): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);
        $invitation = Invitation::where('workspace_id', $workspace->id)->where('token', $token)->first();
        abort_if($invitation === null, 404);
        $allowed = Gate::forUser($user)->allows('manage', $workspace) || $invitation->accepted_by === $user->id;
        abort_unless($allowed, 404);

        return response()->json(['invitationId' => (int) $invitation->id]);
    }

    public function showInvite(Request $request, Workspace $workspace, Invitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);
        $w = $this->inviteWrapFor($workspace, $invitation, $user);

        return response()->json([
            'generation' => $w->generation, 'kdfParams' => $w->kdf_params,
            'dekWrappedInvite' => $w->dek_wrapped_invite, 'dekCheck' => $w->dek_check,
        ]);
    }

    public function acceptInvite(AcceptInviteWrapRequest $request, Workspace $workspace, Invitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);
        $w = $this->inviteWrapFor($workspace, $invitation, $user);
        $d = $request->validated();
        abort_unless((int) $d['generation'] === (int) $w->generation, 422, 'generation mismatch');

        return DB::transaction(function () use ($workspace, $user, $w, $d) {
            WorkspaceVaultKey::updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'generation' => $d['generation']],
                ['kdf_params' => $d['kdfParams'], 'dek_wrapped' => $d['dekWrapped'], 'dek_check' => $d['dekCheck']],
            );
            $w->delete();

            return response()->json(['ok' => true], 201);
        });
    }

    public function rotate(RotateVaultRequest $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('manage', $workspace);
        /** @var User $user */
        $user = $request->user();
        $d = $request->validated();

        return DB::transaction(function () use ($workspace, $user, $d) {
            $locked = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $d['generation'] === $locked->vault_generation + 1, 422, 'stale generation');

            $current = $locked->members()->pluck('user_id')
                ->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $given = collect($d['keys'])->pluck('userId')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            abort_unless($current === $given, 422, 'keys must cover exactly the current members');

            // Whoever holds a recovery set must extend it to the new
            // generation right here; an owner without one simply sends null
            // and the other holders follow up from their own devices.
            $holdsRecovery = WorkspaceVaultRecovery::where('workspace_id', $locked->id)->where('created_by', $user->id)->exists();
            abort_if($holdsRecovery && empty($d['recovery']), 422, 'a recovery holder must supply the recovery wrap');

            // A wrap attached at an older generation is superseded by this
            // rotation — but only for invitations still OPEN. An accepted
            // invitee is a member now: they get the new generation through
            // their rotation code, and their old wrap still opens the notes
            // sealed before it. Expired invitations are not open either —
            // their wrap stays until the prune removes them.
            WorkspaceVaultInvite::where('generation', '<', $d['generation'])
                ->whereIn('invitation_id', Invitation::query()->open()->where('workspace_id', $locked->id)->select('id'))
                ->delete();

            foreach ($d['keys'] as $k) {
                $attrs = [
                    'workspace_id' => $locked->id, 'user_id' => (int) $k['userId'], 'generation' => $d['generation'],
                    'kdf_params' => $k['kdfParams'], 'dek_wrapped' => $k['dekWrapped'], 'dek_check' => $k['dekCheck'],
                ];
                if (($k['kind'] ?? 'own') === 'code') {
                    WorkspaceVaultRotationWrap::create($attrs);
                } else {
                    WorkspaceVaultKey::create($attrs);
                }
            }
            if (! empty($d['recovery'])) {
                WorkspaceVaultRecovery::create([
                    'workspace_id' => $locked->id, 'generation' => $d['generation'], 'created_by' => $user->id,
                    'recovery_salt' => $d['recovery']['recoverySalt'],
                    'dek_wrapped_recovery' => $d['recovery']['dekWrappedRecovery'],
                    'dek_check' => $d['recovery']['dekCheck'],
                ]);
            }
            $locked->forceFill(['vault_generation' => $d['generation'], 'vault_rotation_pending' => false])->save();

            return response()->json(['vaultGeneration' => $d['generation']], 201);
        });
    }

    /**
     * Every owner may hold their own recovery set (one wrap per generation
     * and holder); the wrap for a rotated generation can arrive after the
     * rotation itself, since a rotator without that owner's recovery key
     * never has it in hand.
     */
    public function putRecovery(PutRecoveryWrapRequest $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);
        Gate::authorize('manage', $workspace);
        $d = $request->validated();

        abort_if((int) $d['generation'] > (int) $workspace->vault_generation, 422, 'generation is ahead of the vault');

        try {
            WorkspaceVaultRecovery::create([
                'workspace_id' => $workspace->id, 'generation' => $d['generation'], 'created_by' => $user->id,
                'recovery_salt' => $d['recoverySalt'],
                'dek_wrapped_recovery' => $d['dekWrappedRecovery'],
                'dek_check' => $d['dekCheck'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // The index (workspace, generation, holder) is the single source of
            // truth: two identical concurrent posts end in 201 + 409, never 500.
            abort(409, 'you already hold a recovery wrap for that generation');
        }

        return response()->json(['ok' => true], 201);
    }

    /**
     * Owner + members with their roles, so a client can build the key set a
     * rotation must cover. Any member may read it; the owner is included
     * through their own `workspace_members` row (every user gets one, see
     * `UserObserver`). `name` is the member's display name (may be empty);
     * no e-mail leaves the server. The `$m->user !== null` fallback below is
     * only to satisfy Larastan's nullable relation typing — memberships
     * cascade on user deletion, so it's unreachable at runtime.
     */
    public function members(Request $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);

        $members = $workspace->members()->with('user:id,name')->orderBy('user_id')->get()
            ->map(fn ($m) => [
                'userId' => (int) $m->user_id,
                'role' => $m->role->value,
                'name' => $m->user !== null ? (string) $m->user->name : '',
            ])
            ->values()->all();

        return response()->json(['members' => $members]);
    }
}
