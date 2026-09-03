<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_vault_rotation_wraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->json('kdf_params');
            $table->text('dek_wrapped');
            $table->text('dek_check');
            $table->timestamps();
            // Named: Laravel's default would be 69 chars, over MariaDB's 64-char identifier limit.
            $table->unique(['workspace_id', 'user_id', 'generation'], 'wvrw_workspace_user_generation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_vault_rotation_wraps');
    }
};
