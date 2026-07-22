<?php

namespace App\Models;

use Database\Factories\AccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'role_id', 'organizational_unit_id', 'starts_at', 'ends_at', 'granted_by_user_id', 'delegated_from_grant_id', 'revoked_at', 'revoked_by_user_id', 'revocation_reason'])]
class AccessGrant extends Model
{
    /** @use HasFactory<AccessGrantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return BelongsTo<AccessGrant, $this> */
    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'delegated_from_grant_id');
    }

    /** @return HasMany<AccessGrant, $this> */
    public function delegations(): HasMany
    {
        return $this->hasMany(self::class, 'delegated_from_grant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
