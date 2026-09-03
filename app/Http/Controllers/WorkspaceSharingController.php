<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVaultKey;
use App\Models\WorkspaceVaultRecovery;
use App\Models\WorkspaceVaultRotationWrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkspaceSharingController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('workspaces.index', ['workspaces' => $user->workspaces()->get()]);
    }

    public function share(Workspace $workspace): View
    {
        Gate::authorize('manage', $workspace);

        return view('workspaces.share', [
            'workspace' => $workspace,
            'members' => $workspace->members()->with('user')->get(),
            'invitations' => $workspace->invitations()->open()->get(),
            'roles' => WorkspaceRole::cases(),
        ]);
    }

    public function storeInvitation(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manage', $workspace);

        $data = $request->validate([
            'role' => ['required', 'in:owner,editor,viewer'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $invite = Invitation::create([
            'workspace_id' => $workspace->id,
            'token' => Str::random(40),
            'role' => $data['role'],
            'invited_by' => $user->id,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
        ]);

        return back()->with('invite_link', url("/invite/{$invite->token}"));
    }

    public function destroyInvitation(Workspace $workspace, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('manage', $workspace);
        abort_unless($invitation->workspace_id === $workspace->id, 404);
        $invitation->delete();

        return back()->with('status', 'Invitation revoked.');
    }

    public function updateMember(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        Gate::authorize('manage', $workspace);
        abort_unless($member->workspace_id === $workspace->id, 404);

        $data = $request->validate(['role' => ['required', 'in:owner,editor,viewer']]);
        $newRole = WorkspaceRole::from($data['role']);

        if ($this->wouldOrphanOwners($workspace, $member, $newRole)) {
            return back()->with('error', 'A workspace must keep at least one owner.');
        }

        $member->update(['role' => $newRole]);

        return back()->with('status', 'Member updated.');
    }

    public function destroyMember(Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        Gate::authorize('manage', $workspace);
        abort_unless($member->workspace_id === $workspace->id, 404);

        if ($this->wouldOrphanOwners($workspace, $member, null)) {
            return back()->with('error', 'A workspace must keep at least one owner.');
        }

        DB::transaction(function () use ($workspace, $member) {
            $member->delete();

            if ($workspace->vault_generation > 0) {
                WorkspaceVaultKey::where('workspace_id', $workspace->id)->where('user_id', $member->user_id)->delete();
                WorkspaceVaultRotationWrap::where('workspace_id', $workspace->id)->where('user_id', $member->user_id)->delete();
                WorkspaceVaultRecovery::where('workspace_id', $workspace->id)->where('created_by', $member->user_id)->delete();
                $workspace->forceFill(['vault_rotation_pending' => true])->save();
            }
        });

        return back()->with('status', 'Member removed.');
    }

    /** True if removing (null role) or re-roling this member would leave zero owners. */
    private function wouldOrphanOwners(Workspace $workspace, WorkspaceMember $member, ?WorkspaceRole $newRole): bool
    {
        if ($member->role !== WorkspaceRole::Owner) {
            return false;
        }
        if ($newRole === WorkspaceRole::Owner) {
            return false;
        }

        return $workspace->members()->where('role', WorkspaceRole::Owner->value)->count() <= 1;
    }
}
