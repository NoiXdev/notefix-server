<?php

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folderId' => $this->folder_id,
            'content' => $this->content,
            'pinned' => $this->pinned,
            'archived' => $this->archived,
            'color' => $this->color,
            'dueAt' => $this->due_at?->toIso8601String(),
            'position' => $this->position,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'deletedAt' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
