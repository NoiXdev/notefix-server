<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceController extends Controller
{
    /**
     * List the authenticated user's workspaces (with their role on each).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        // workspaces() is the belongsToMany via workspace_members (pivot 'role').
        // The personal workspace is included because the owner is attached as a
        // member (see UserObserver).
        return WorkspaceResource::collection($user->workspaces()->get());
    }
}
