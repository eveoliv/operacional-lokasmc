<?php

namespace App\Models;

use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RegistrationStatus $status
 * @property RegistrationSource $source
 */
#[Fillable(['event_id', 'person_id', 'status', 'source', 'operated_by_user_id', 'eligible_event_audience_id', 'eligible_organizational_unit_id', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason'])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'source' => RegistrationSource::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /** @param Builder<Registration> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Active);
    }

    /** @param Builder<Registration> $query */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Cancelled);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operated_by_user_id');
    }

    /** @return BelongsTo<EventAudience, $this> */
    public function eligibleEventAudience(): BelongsTo
    {
        return $this->belongsTo(EventAudience::class, 'eligible_event_audience_id');
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function eligibleOrganizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'eligible_organizational_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
