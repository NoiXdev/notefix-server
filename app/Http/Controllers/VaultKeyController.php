<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInviteWrapRequest;
use App\Http\Requests\AttachInviteWrapRequest;
use App\Http\Requests\CreateVaultRequest;
use App\Http\Requests\PutMyVaultKeyRequest;
use App\Http\Requests\RotateVaultRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVaultInvite;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VaultKeyController extends Controller
{
    public static function authorizeMember(Workspace $workspace, User $user): void
    {
        $isMember = $workspace->owner_id === $user->id
            || $workspace->members()->where('user_id', $user->id)->exists();
        abort_unless($isMember, 403);
    }

    public function create(CreateVaultRequest $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        self::authorizeMember($workspace, $user);
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
        abort_if($key === null, 404, 'no key for that generation');
        $key->forceFill(['kdf_params' => $d['kdfParams'], 'dek_wrapped' => $d['dekWrapped'], 'dek_check' => $d['dekCheck']])->save();

        return response()->json(['ok' => true]);
    }

    public function attachInvite(AttachInviteWrapRequest $request, Workspace $workspace, Invitation $invitation): JsonResponse
    {
        Gate::authorize('manage', $workspace);
        abort_unless($invitation->workspace_id === $workspace->id, 404);
        abort_unless($invitation->isOpen(), 410, 'invitation is not open');
        $d = $request->validated();
        abort_unless((int) $d['generation'] === (int) $workspace->vault_generation, 422, 'generation mismatch');
        abort_if(WorkspaceVaultInvite::where('invitation_id', $invitation->id)->exists(), 409, 'wrap already attached');
        WorkspaceVaultInvite::create([
            'invitation_id' => $invitation->id, 'generation' => $d['generation'],
            'kdf_params' => $d['kdfParams'], 'dek_wrapped_invite' => $d['dekWrapped'], 'dek_check' => $d['dekCheck'],
        ]);

        return response()->json(['ok' => true], 201);
    }

    private function inviteWrapFor(Workspace $workspace, Invitation $invitation, User $user): WorkspaceVaultInvite
    {
        abort_unless($invitation->workspace_id === $workspace->id && $invitation->accepted_by === $user->id, 404);
        $wrap = WorkspaceVaultInvite::where('invitation_id', $invitation->id)->first();
        abort_if($wrap === null, 404);

        return $wrap;
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

            $current = $locked->members()->pluck('user_id')->push($locked->owner_id)
                ->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $given = collect($d['keys'])->pluck('userId')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            abort_unless($current === $given, 422, 'keys must cover exactly the current members');

            $creator = WorkspaceVaultRecovery::where('workspace_id', $locked->id)->where('generation', 1)->value('created_by');
            $creator = $creator === null ? null : (int) $creator;
            abort_if($creator === $user->id && empty($d['recovery']), 422, 'the vault creator must supply the recovery wrap');

            foreach ($d['keys'] as $k) {
                WorkspaceVaultKey::create([
                    'workspace_id' => $locked->id, 'user_id' => (int) $k['userId'], 'generation' => $d['generation'],
                    'kdf_params' => $k['kdfParams'], 'dek_wrapped' => $k['dekWrapped'], 'dek_check' => $k['dekCheck'],
                ]);
            }
            if (! empty($d['recovery'])) {
                WorkspaceVaultRecovery::create([
                    'workspace_id' => $locked->id, 'generation' => $d['generation'], 'created_by' => $creator ?? $user->id,
                    'recovery_salt' => $d['recovery']['recoverySalt'],
                    'dek_wrapped_recovery' => $d['recovery']['dekWrappedRecovery'],
                    'dek_check' => $d['recovery']['dekCheck'],
                ]);
            }
            $locked->forceFill(['vault_generation' => $d['generation'], 'vault_rotation_pending' => false])->save();

            return response()->json(['vaultGeneration' => $d['generation']], 201);
        });
    }
}
