<?php

namespace App\Models;

use Database\Factories\OrganizationalUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organizational_unit_type_id', 'parent_id', 'code', 'name', 'is_active', 'archived_at'])]
class OrganizationalUnit extends Model
{
    /** @use HasFactory<OrganizationalUnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrganizationalUnitType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnitType::class, 'organizational_unit_type_id');
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<OrganizationalUnit, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsToMany<OrganizationalUnit, $this> */
    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'organizational_unit_closure', 'descendant_id', 'ancestor_id')->withPivot('depth');
    }

    /** @return BelongsToMany<OrganizationalUnit, $this> */
    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'organizational_unit_closure', 'ancestor_id', 'descendant_id')->withPivot('depth');
    }

    /** @return HasMany<AccessGrant, $this> */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    /** @return HasMany<Person, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<EventAudience, $this> */
    public function eventAudiences(): HasMany
    {
        return $this->hasMany(EventAudience::class);
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
