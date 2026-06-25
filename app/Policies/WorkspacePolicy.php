<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Any member may read a workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRole($user) !== null;
    }

    /**
     * Only owners and editors may write (push). Viewers are read-only.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return in_array(
            $workspace->memberRole($user),
            [WorkspaceRole::Owner, WorkspaceRole::Editor],
            true,
        );
    }

    /**
     * Only owners may manage sharing (members + invitations).
     */
    public function manage(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRole($user) === WorkspaceRole::Owner;
    }
}
