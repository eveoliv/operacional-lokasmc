<?php

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property EventStatus $status
 */
#[Fillable(['organizational_unit_id', 'title', 'description', 'status', 'starts_at', 'ends_at', 'location', 'capacity', 'archived_at'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @param  Builder<Event>  $query
     * @param  Collection<int, int>|array<int, int>  $unitIds
     */
    public function scopeWithinUnits(Builder $query, Collection|array $unitIds): void
    {
        $query->whereIn('organizational_unit_id', $unitIds);
    }

    /** @param Builder<Event> $query */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /** @return HasMany<EventAudience, $this> */
    public function audiences(): HasMany
    {
        return $this->hasMany(EventAudience::class);
    }

    /** @return HasMany<Registration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
