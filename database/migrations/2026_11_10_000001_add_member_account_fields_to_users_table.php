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
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['member_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
            $table->dropColumn('must_change_password');
        });
    }
};
