<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = ['name', 'owner_id', 'type'];

    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * This user's role in the workspace, or null if they are not a member.
     */
    public function memberRole(User $user): ?WorkspaceRole
    {
        return $this->members()->where('user_id', $user->id)->first()?->role;
    }
}
