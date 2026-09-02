<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('protected')->default(false)->after('content');
            $table->string('title')->default('')->after('protected');
        });
        Schema::table('folders', function (Blueprint $table) {
            $table->boolean('locked')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('notes', fn (Blueprint $t) => $t->dropColumn(['protected', 'title']));
        Schema::table('folders', fn (Blueprint $t) => $t->dropColumn('locked'));
    }
};
