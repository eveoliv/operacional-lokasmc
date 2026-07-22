<?php

namespace App\Actions;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetAttendanceSessionLock
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, AttendanceSession $session, bool $locked): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $session, $locked): AttendanceSession {
            $session = AttendanceSession::query()->with('event.organizationalUnit')->lockForUpdate()->whereKey($session->getKey())->firstOrFail();
            if ($session->archived_at !== null) {
                throw ValidationException::withMessages(['attendance_session' => 'A sessão está arquivada.']);
            }
            if (($session->locked_at !== null) === $locked) {
                return $session;
            }

            $old = $session->only(['locked_at', 'locked_by_user_id']);
            $session->forceFill([
                'locked_at' => $locked ? now() : null,
                'locked_by_user_id' => $locked ? $actor->getKey() : null,
            ])->save();
            $this->audit->log($locked ? 'attendance_session.locked' : 'attendance_session.unlocked', $session, $actor, $session->event->organizationalUnit, $old, $session->only(array_keys($old)));

            return $session;
        });
    }
}
