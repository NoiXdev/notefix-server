<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Last-write-wins compares `updated_at`, and the client stamps edits in
     * milliseconds. Whole-second columns made two edits within one second
     * indistinguishable, so the newer one could lose. Existing rows keep
     * their value (with .000).
     */
    public function up(): void
    {
        foreach (['notes', 'folders'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->timestamp('updated_at', 3)->nullable()->change();
                $t->timestamp('deleted_at', 3)->nullable()->change();
                if ($table === 'notes') {
                    $t->timestamp('due_at', 3)->nullable()->change();
                }
            });
        }
    }

    // Truncates the milliseconds again.
    public function down(): void
    {
        foreach (['notes', 'folders'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->timestamp('updated_at')->nullable()->change();
                $t->timestamp('deleted_at')->nullable()->change();
                if ($table === 'notes') {
                    $t->timestamp('due_at')->nullable()->change();
                }
            });
        }
    }
};
