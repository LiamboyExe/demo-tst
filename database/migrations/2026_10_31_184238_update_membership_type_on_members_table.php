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
        Schema::table('members', function (Blueprint $table) {
            $table->string('membership_type', 50)->default('Carnal')->change();
        });

        DB::table('members')->where('membership_type', 'Premium')->update(['membership_type' => 'High Table Council']);
        DB::table('members')->where('membership_type', 'Senior')->update(['membership_type' => 'Pointman']);
        DB::table('members')->where('membership_type', 'Student')->update(['membership_type' => 'Carnala']);
        DB::table('members')->where('membership_type', 'Regular')->update(['membership_type' => 'Carnal']);
        DB::table('members')
            ->whereNotIn('membership_type', [
                'High Table Council',
                'Shot Caller',
                'Alas',
                'Pointman',
                'Carnal',
                'Carnala',
            ])
            ->update(['membership_type' => 'Carnal']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('members')->where('membership_type', 'High Table Council')->update(['membership_type' => 'Premium']);
        DB::table('members')->where('membership_type', 'Shot Caller')->update(['membership_type' => 'Premium']);
        DB::table('members')->where('membership_type', 'Pointman')->update(['membership_type' => 'Senior']);
        DB::table('members')->where('membership_type', 'Carnala')->update(['membership_type' => 'Student']);
        DB::table('members')->whereIn('membership_type', ['Alas', 'Carnal'])->update(['membership_type' => 'Regular']);

        Schema::table('members', function (Blueprint $table) {
            $table->string('membership_type', 20)->default('Regular')->change();
        });
    }
};
