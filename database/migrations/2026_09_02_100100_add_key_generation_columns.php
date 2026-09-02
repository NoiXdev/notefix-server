<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $t) {
            $t->unsignedInteger('key_gen')->nullable()->after('title');
        });
        Schema::table('workspaces', function (Blueprint $t) {
            $t->unsignedInteger('vault_generation')->default(0)->after('type');
            $t->boolean('vault_rotation_pending')->default(false)->after('vault_generation');
        });
    }

    public function down(): void
    {
        Schema::table('notes', fn (Blueprint $t) => $t->dropColumn('key_gen'));
        Schema::table('workspaces', fn (Blueprint $t) => $t->dropColumn(['vault_generation', 'vault_rotation_pending']));
    }
};
