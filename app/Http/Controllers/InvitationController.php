<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(Invitation $invitation): View
    {
        if (! $invitation->isOpen()) {
            return view('invite.error');
        }

        return view('invite.show', [
            'invitation' => $invitation,
            'workspace' => $invitation->workspace,
        ]);
    }

    public function accept(Request $request, Invitation $invitation): RedirectResponse
    {
        if (! $invitation->isOpen()) {
            return redirect('/workspaces')->with('error', 'This invitation is no longer valid.');
        }

        /** @var User $user */
        $user = $request->user();

        // Idempotent: only add a membership if the user isn't already in the workspace.
        $existing = WorkspaceMember::where('workspace_id', $invitation->workspace_id)
            ->where('user_id', $user->id)->first();
        if ($existing === null) {
            WorkspaceMember::create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);
        }

        $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

        return redirect('/workspaces')->with('status', 'You joined the workspace.');
    }
}
