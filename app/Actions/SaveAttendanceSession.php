<?php

namespace App\Actions;

use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAttendanceSession
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, Event $event, array $attributes, ?AttendanceSession $session = null): AttendanceSession
    {
        if ($session !== null && $session->event_id !== $event->getKey()) {
            throw ValidationException::withMessages(['event' => 'A sessão não pertence a este evento.']);
        }

        return DB::transaction(function () use ($actor, $event, $attributes, $session): AttendanceSession {
            if ($session !== null) {
                $session = AttendanceSession::query()->lockForUpdate()->whereKey($session->getKey())->firstOrFail();
                if ($session->locked_at !== null || $session->archived_at !== null) {
                    throw ValidationException::withMessages(['attendance_session' => 'A sessão está bloqueada ou arquivada.']);
                }
                $old = $session->only(array_keys($attributes));
                $session->update($attributes);
                $action = 'attendance_session.updated';
            } else {
                $old = [];
                $session = $event->attendanceSessions()->create($attributes);
                $action = 'attendance_session.created';
            }
            $this->audit->log($action, $session, $actor, $event->organizationalUnit, $old, $session->only(array_keys($attributes)));

            return $session;
        });
    }
}
