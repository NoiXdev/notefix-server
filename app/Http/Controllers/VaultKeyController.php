<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateVaultRequest;
use App\Http\Requests\PutMyVaultKeyRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
}
