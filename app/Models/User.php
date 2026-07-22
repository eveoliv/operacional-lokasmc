<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $disabled_at
 * @property string|null $disabled_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'email_verified_at', 'disabled_at', 'disabled_reason'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'disabled_reason'])]
class User extends Authenticatable implements PasskeyUser
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
            'two_factor_confirmed_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /** @return HasOne<Person, $this> */
    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }

    /** @return HasMany<AccessGrant, $this> */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    /** @return HasMany<AccessGrant, $this> */
    public function grantedAccessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class, 'granted_by_user_id');
    }

    /** @return HasMany<AccessGrant, $this> */
    public function revokedAccessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class, 'revoked_by_user_id');
    }

    /** @return HasMany<Registration, $this> */
    public function operatedRegistrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'operated_by_user_id');
    }

    /** @return HasMany<Registration, $this> */
    public function cancelledRegistrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function lockedAttendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'locked_by_user_id');
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function operatedAttendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'operated_by_user_id');
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }
}
