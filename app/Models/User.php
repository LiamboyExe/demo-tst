<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property UserRole $role
 * @property int|null $branch_id
 * @property int|null $member_id
 * @property string|null $temp_username
 * @property string|null $temp_password
 * @property Carbon|null $activated_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member|null $member
 */
#[Fillable(['name', 'username', 'email', 'password', 'role', 'branch_id', 'member_id', 'temp_username', 'temp_password', 'activated_at'])]
#[Hidden(['password', 'temp_username', 'temp_password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'temp_password' => 'encrypted',
            'role' => UserRole::class,
            'activated_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
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
     * The member record this account belongs to when it is a member account.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isMainAdmin(): bool
    {
        return $this->role === UserRole::Main;
    }

    public function isBranchAdmin(): bool
    {
        return $this->role === UserRole::Local;
    }

    public function isMember(): bool
    {
        return $this->role === UserRole::Member;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Main || $this->role === UserRole::Local;
    }

    /**
     * Whether the account has finished activation. Admin accounts are always
     * activated. Member accounts are activated once their permanent credentials
     * are created.
     */
    public function isActivated(): bool
    {
        return (! $this->isMember() && $this->member_id === null) || $this->activated_at !== null;
    }

    /**
     * Whether the account is waiting for the member to turn its temporary
     * credentials into permanent ones on their NFC profile. Only member
     * accounts go through activation.
     */
    public function isPendingActivation(): bool
    {
        return ($this->isMember() || $this->member_id !== null) && $this->activated_at === null;
    }
}
