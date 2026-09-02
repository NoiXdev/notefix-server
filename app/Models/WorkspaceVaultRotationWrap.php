<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceVaultRotationWrap extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'generation', 'kdf_params', 'dek_wrapped', 'dek_check'];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'kdf_params' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
