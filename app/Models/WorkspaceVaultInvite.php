<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceVaultInvite extends Model
{
    protected $fillable = ['invitation_id', 'generation', 'kdf_params', 'dek_wrapped_invite', 'dek_check'];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'kdf_params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
