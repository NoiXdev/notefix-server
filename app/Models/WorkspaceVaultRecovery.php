<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceVaultRecovery extends Model
{
    protected $table = 'workspace_vault_recovery';

    protected $fillable = ['workspace_id', 'generation', 'created_by', 'recovery_salt', 'dek_wrapped_recovery', 'dek_check'];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
