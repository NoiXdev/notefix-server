<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_vault_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('generation');
            $t->json('kdf_params');
            $t->text('dek_wrapped');
            $t->text('dek_check');
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id', 'generation']);
        });
        Schema::create('workspace_vault_recovery', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('generation');
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->text('recovery_salt');
            $t->text('dek_wrapped_recovery');
            $t->text('dek_check');
            $t->timestamps();
            $t->unique(['workspace_id', 'generation']);
        });
        Schema::create('workspace_vault_invites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invitation_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('generation');
            $t->json('kdf_params');
            $t->text('dek_wrapped_invite');
            $t->text('dek_check');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_vault_invites');
        Schema::dropIfExists('workspace_vault_recovery');
        Schema::dropIfExists('workspace_vault_keys');
    }
};
