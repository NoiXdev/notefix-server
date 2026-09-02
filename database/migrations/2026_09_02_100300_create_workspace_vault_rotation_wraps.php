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
            $table->unique(['workspace_id', 'user_id', 'generation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_vault_rotation_wraps');
    }
};
