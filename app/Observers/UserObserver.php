<?php

namespace App\Observers;

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Give every new user their own personal workspace, with themselves as the
     * owner member. Wrapped in a transaction so a workspace never ends up
     * without its owner membership.
     */
    public function created(User $user): void
    {
        DB::transaction(function () use ($user) {
            $workspace = $user->ownedWorkspaces()->create([
                'name' => "{$user->name}'s Notes",
                'type' => WorkspaceType::Personal,
            ]);

            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);
        });
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
