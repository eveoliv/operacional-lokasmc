<?php

namespace App\Actions;

use App\Enums\RegistrationStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Registration;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScopeAuthorizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertAttendanceBatch
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ScopeAuthorizer $authorizer,
    ) {}

    /**
     * @param  list<array{registration_id:int, status:string, checked_in_at?:mixed, checked_out_at?:mixed, notes?:mixed}>  $items
     * @return Collection<int, AttendanceRecord>
     */
    public function handle(User $actor, AttendanceSession $session, array $items): Collection
    {
        return DB::transaction(function () use ($actor, $session, $items): Collection {
            $session = AttendanceSession::query()->with('event.organizationalUnit')->lockForUpdate()->whereKey($session->getKey())->firstOrFail();

            if ($session->locked_at !== null || $session->archived_at !== null) {
                $this->fail('attendance_session', 'A sessão está bloqueada ou arquivada.');
            }

            $ids = collect($items)->pluck('registration_id');
            if ($ids->duplicates()->isNotEmpty()) {
                $this->fail('records', 'Cada inscrição pode aparecer apenas uma vez no lote.');
            }

            $registrations = Registration::query()->with('eligibleEventAudience')->whereKey($ids)->lockForUpdate()->get()->keyBy('id');
            if ($registrations->count() !== $ids->count()) {
                $this->fail('records', 'Uma ou mais inscrições são inválidas.');
            }

            foreach ($registrations as $registration) {
                if ($registration->event_id !== $session->event_id || $registration->status !== RegistrationStatus::Active) {
                    $this->fail('records', 'Todas as inscrições devem estar ativas e pertencer ao evento da sessão.');
                }

                $audience = $registration->eligibleEventAudience;
                if ($audience === null || $audience->event_id !== $registration->event_id || $registration->eligible_organizational_unit_id === null) {
                    $this->fail('records', 'A audiência elegível da inscrição é inconsistente.');
                }

                $eligibleUnit = $registration->eligible_organizational_unit_id;
                $audienceMatches = $audience->include_descendants
                    ? $this->authorizer->contains($audience->organizational_unit_id, $eligibleUnit)
                    : $audience->organizational_unit_id === $eligibleUnit;
                if (! $audienceMatches) {
                    $this->fail('records', 'A unidade elegível não pertence à audiência da inscrição.');
                }
            }

            return collect($items)->map(function (array $item) use ($actor, $session): AttendanceRecord {
                $attributes = [
                    'status' => $item['status'],
                    'checked_in_at' => $item['checked_in_at'] ?? null,
                    'checked_out_at' => $item['checked_out_at'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'operated_by_user_id' => $actor->getKey(),
                ];
                $record = AttendanceRecord::query()->where('attendance_session_id', $session->getKey())
                    ->where('registration_id', $item['registration_id'])->lockForUpdate()->first();
                $old = $record?->only(array_keys($attributes)) ?? [];

                if ($record === null) {
                    $record = AttendanceRecord::query()->create(['attendance_session_id' => $session->getKey(), 'registration_id' => $item['registration_id'], ...$attributes]);
                    $action = 'attendance_record.created';
                } elseif ($this->equivalent($record, $attributes)) {
                    return $record;
                } else {
                    $record->update($attributes);
                    $action = 'attendance_record.updated';
                }

                $this->audit->log($action, $record, $actor, $session->event->organizationalUnit, $old, $record->only(array_keys($attributes)), ['attendance_session_id' => $session->getKey()]);

                return $record;
            });
        });
    }

    /** @param array<string, mixed> $attributes */
    private function equivalent(AttendanceRecord $record, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            $current = $record->{$key};
            if (($current instanceof \DateTimeInterface ? $current->format('Y-m-d H:i:s') : $current->value ?? $current)
                != ($value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value)) {
                return false;
            }
        }

        return true;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
