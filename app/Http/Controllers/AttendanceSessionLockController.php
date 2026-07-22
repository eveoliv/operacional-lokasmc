<?php

namespace App\Http\Controllers;

use App\Actions\SetAttendanceSessionLock;
use App\Http\Requests\Attendance\LockAttendanceSessionRequest;
use App\Http\Requests\Attendance\UnlockAttendanceSessionRequest;
use App\Models\AttendanceSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AttendanceSessionLockController extends Controller
{
    public function lock(LockAttendanceSessionRequest $request, AttendanceSession $attendanceSession, SetAttendanceSessionLock $action): RedirectResponse
    {
        $action->handle($request->user(), $attendanceSession, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sessão bloqueada.']);

        return back();
    }

    public function unlock(UnlockAttendanceSessionRequest $request, AttendanceSession $attendanceSession, SetAttendanceSessionLock $action): RedirectResponse
    {
        $action->handle($request->user(), $attendanceSession, false);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sessão desbloqueada.']);

        return back();
    }
}
