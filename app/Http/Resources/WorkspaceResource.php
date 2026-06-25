<?php

namespace App\Http\Resources;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workspace
 */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Role comes from the workspace_members pivot loaded by the controller.
            // @phpstan-ignore property.notFound (dynamic belongsToMany pivot)
            'role' => $this->pivot?->role,
        ];
    }
}
