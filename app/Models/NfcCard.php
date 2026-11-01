<?php

namespace App\Models;

use App\Enums\NfcCardStatus;
use App\Policies\NfcCardPolicy;
use Database\Factories\NfcCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property string $card_uid
 * @property string $profile_url
 * @property NfcCardStatus $card_status
 * @property Carbon $issued_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['member_id', 'card_uid', 'profile_url', 'card_status', 'issued_date', 'notes'])]
#[UsePolicy(NfcCardPolicy::class)]
class NfcCard extends Model
{
    /** @use HasFactory<NfcCardFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_status' => NfcCardStatus::class,
            'issued_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Resolve the NFC profile URL dynamically to the active application host
     * if it points to an internal profile route or relative path, ensuring existing
     * cards stored with local IPs or temporary domains point to the current environment URL.
     *
     * @return Attribute<string, string>
     */
    protected function profileUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                if (empty($value)) {
                    return $this->member ? route('profile.show', $this->member) : '';
                }

                $path = parse_url($value, PHP_URL_PATH);

                if (is_string($path) && str_starts_with($path, '/p/')) {
                    return url($path);
                }

                return $value;
            },
        );
    }
}
