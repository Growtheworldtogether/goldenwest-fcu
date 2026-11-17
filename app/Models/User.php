<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'first_name', 'last_name', 'phone', 'dob', 'address_line1', 'city', 'state', 'postal_code'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /** Accounts this user holds, through account_holders. */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_holders')->withPivot(['role', 'status']);
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    /** Recovery codes for two-factor sign-in, decrypted. */
    public function recoveryCodes(): array
    {
        return $this->two_factor_recovery_codes
            ? json_decode(decrypt($this->two_factor_recovery_codes), true)
            : [];
    }

    public function adminProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function adminTitle(): ?string
    {
        return $this->isAdmin() ? $this->adminProfile?->title : null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

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
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'dob' => 'date',
            'notification_preferences' => 'array',
        ];
    }
}
