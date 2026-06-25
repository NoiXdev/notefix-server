<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('folder_id')->nullable();
            $table->longText('content');
            $table->boolean('pinned')->default(false);
            $table->boolean('archived')->default(false);
            $table->string('color')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->integer('position')->default(0);
            $table->unsignedBigInteger('sync_seq')->index();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
