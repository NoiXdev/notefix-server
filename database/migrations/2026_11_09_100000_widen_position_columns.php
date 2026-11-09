<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The client orders notes by `position`, which defaults to -updated_at in
     * milliseconds — far outside a 32-bit INT. MariaDB rejected every such
     * push with "Out of range value" (SQLite, used by the tests, never did).
     * Folders get the same width: the client stores both as i64.
     */
    public function up(): void
    {
        foreach (['notes', 'folders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->bigInteger('position')->default(0)->change();
            });
        }
    }

    // Narrowing back fails (or truncates) once a millisecond position exists.
    public function down(): void
    {
        foreach (['notes', 'folders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->integer('position')->default(0)->change();
            });
        }
    }
};
