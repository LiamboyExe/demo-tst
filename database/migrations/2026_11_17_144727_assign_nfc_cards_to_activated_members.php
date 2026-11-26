<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Assign an NFC card to every member whose account is already activated
     * but who has no card yet.
     */
    public function up(): void
    {
        DB::table('members')
            ->whereNull('deleted_at')
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('users')
                ->whereColumn('users.member_id', 'members.id')
                ->whereNotNull('users.activated_at'))
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('nfc_cards')
                ->whereColumn('nfc_cards.member_id', 'members.id'))
            ->select(['id', 'profile_slug'])
            ->orderBy('id')
            ->chunkById(100, function ($members): void {
                $now = now();

                DB::table('nfc_cards')->insert($members->map(fn ($member): array => [
                    'member_id' => $member->id,
                    'card_uid' => 'NFC-'.Str::upper(Str::random(8)),
                    'profile_url' => '/p/'.$member->profile_slug,
                    'card_status' => 'Active',
                    'issued_date' => $now->toDateString(),
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Assigned cards are real member data; keep them on rollback.
    }
};
