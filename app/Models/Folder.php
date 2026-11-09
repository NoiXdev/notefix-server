<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $updated_at
 */
class Folder extends Model
{
    use HasUuids, SoftDeletes;

    public $timestamps = false;

    /** Millisecond precision: last-write-wins compares `updated_at`. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'id', 'workspace_id', 'parent_id', 'name', 'locked', 'icon', 'color', 'sort', 'position', 'sync_seq', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'locked' => 'boolean',
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
}
