<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WorkspaceRole $role
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 */
class Invitation extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'token', 'role', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Open = not yet accepted and not past its (optional) expiry. The PHP twin of the `open()` scope. */
    public function isOpen(): bool
    {
        return $this->accepted_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Open = not yet accepted and not past its (optional) expiry — the query
     * twin of [`isOpen()`]; keep the two in step.
     *
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
