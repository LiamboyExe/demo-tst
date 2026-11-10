<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Temporary credentials handed to the member for first-time
            // activation. temp_password holds an encrypted copy so an
            // authorized administrator can show it again while pending.
            $table->string('temp_username', 60)->nullable()->unique()->after('username');
            $table->text('temp_password')->nullable()->after('password');
            $table->timestamp('activated_at')->nullable()->after('remember_token');
        });

        // Every account that exists before this migration already signs in
        // with its own username and password, so it counts as activated. The
        // superseded must_change_password flag (and its forced-change flow)
        // is replaced by the activation workflow.
        DB::table('users')->whereNull('activated_at')->update([
            'activated_at' => DB::raw('created_at'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('remember_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['temp_username', 'temp_password', 'activated_at']);
        });
    }
};
