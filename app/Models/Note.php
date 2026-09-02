<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $updated_at
 * @property Carbon|null $due_at
 */
class Note extends Model
{
    use HasUuids, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'id', 'workspace_id', 'folder_id', 'content', 'protected', 'title', 'key_gen', 'pinned', 'archived', 'color',
        'due_at', 'position', 'sync_seq', 'updated_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'protected' => 'boolean',
            'key_gen' => 'integer',
            'pinned' => 'boolean',
            'archived' => 'boolean',
            'due_at' => 'datetime',
            'updated_at' => 'datetime',
            'position' => 'integer',
            'sync_seq' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Revision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class);
    }
}
