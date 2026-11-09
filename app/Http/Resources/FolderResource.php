<?php

namespace App\Http\Resources;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Folder
 */
class FolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parent_id,
            'name' => $this->name,
            'locked' => $this->locked,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort' => $this->sort,
            'position' => $this->position,
            'updatedAt' => $this->updated_at?->toRfc3339String(true), // with milliseconds
            'deletedAt' => $this->deleted_at?->toRfc3339String(true), // with milliseconds
        ];
    }
}
