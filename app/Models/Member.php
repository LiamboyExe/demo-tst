<?php

namespace App\Models;

use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Policies\MemberPolicy;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $hood_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $alias
 * @property Carbon|null $birthdate
 * @property string|null $avatar_path
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property MembershipType $membership_type
 * @property MemberStatus $status
 * @property Carbon $join_date
 * @property string $profile_slug
 * @property bool $approved
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read string $initials
 * @property-read int|null $age
 * @property-read string|null $avatar_url
 */
#[Fillable([
    'branch_id',
    'hood_id',
    'first_name',
    'last_name',
    'alias',
    'birthdate',
    'avatar_path',
    'email',
    'phone',
    'address',
    'membership_type',
    'status',
    'join_date',
    'profile_slug',
    'approved',
])]
#[Appends(['full_name', 'initials', 'age', 'avatar_url'])]
#[UsePolicy(MemberPolicy::class)]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'birthdate' => 'date',
            'join_date' => 'date',
            'membership_type' => MembershipType::class,
            'status' => MemberStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Hood, $this>
     */
    public function hood(): BelongsTo
    {
        return $this->belongsTo(Hood::class);
    }

    /**
     * @return HasOne<NfcCard, $this>
     */
    public function nfcCard(): HasOne
    {
        return $this->hasOne(NfcCard::class);
    }

    /**
     * The member's login account, when an administrator has created one.
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Limit the query to the branches the given user may see.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isBranchAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => strtoupper(
            mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1),
        ));
    }

    /**
     * Age derived from the member's birthdate.
     *
     * @return Attribute<int|null, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->birthdate?->age);
    }

    /**
     * Public URL for the member's uploaded avatar, if any.
     *
     * The path is returned relative so it resolves against whatever host the
     * app is currently served from (local domain, LAN IP, or tunnel).
     *
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (blank($this->avatar_path)) {
                return null;
            }

            $url = Storage::disk('public')->url($this->avatar_path);

            if (config('filesystems.disks.public.driver') === 'local') {
                return parse_url($url, PHP_URL_PATH) ?: $url;
            }

            return $url;
        });
    }
}
