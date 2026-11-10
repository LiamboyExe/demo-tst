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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('must_change_password')->default(false)->after('remember_token');
        });

        // One login account per member. Existing (admin) rows keep member_id
        // NULL, and SQLite/MySQL allow multiple NULLs in a unique index.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL requires the foreign key to be dropped before the unique
        // index that backs it, and the index before the column.
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['member_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['member_id', 'must_change_password']);
        });
    }
};
