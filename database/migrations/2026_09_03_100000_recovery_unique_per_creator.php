<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_vault_recovery', function (Blueprint $t) {
            $t->dropUnique(['workspace_id', 'generation']);
            $t->unique(['workspace_id', 'generation', 'created_by'], 'wvr_workspace_generation_creator_unique');
        });
    }

    // Re-adding the (workspace_id, generation) unique fails once two owners hold
    // rows for one generation, and since DDL isn't transactional on MySQL/MariaDB
    // the table is then left without any unique index — roll back only before
    // per-owner rows exist.
    public function down(): void
    {
        Schema::table('workspace_vault_recovery', function (Blueprint $t) {
            $t->dropUnique('wvr_workspace_generation_creator_unique');
            $t->unique(['workspace_id', 'generation']);
        });
    }
};
