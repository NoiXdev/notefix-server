<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;

/**
 * Deletes invitations that expired more than the grace period ago and were
 * never accepted. Their vault wraps (`workspace_vault_invites`) go with them
 * through the foreign key's cascade — a rotation keeps the wrap of an
 * expired invitation (it is not "open"), so this is what finally retires it.
 * Invitations without an expiry and accepted ones are never touched.
 */
class PruneInvitations extends Command
{
    protected $signature = 'invitations:prune {--days=7 : Days after expiry before an unaccepted invitation is deleted}';

    protected $description = 'Delete unaccepted invitations that expired more than the grace period ago (their vault wraps cascade)';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $count = Invitation::query()
            ->whereNull('accepted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$count} expired invitation(s).");

        return self::SUCCESS;
    }
}
