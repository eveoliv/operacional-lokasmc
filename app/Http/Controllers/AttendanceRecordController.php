<?php

namespace App\Http\Controllers;

use App\Actions\UpsertAttendanceBatch;
use App\Http\Requests\Attendance\BatchAttendanceRequest;
use App\Models\AttendanceSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AttendanceRecordController extends Controller
{
    public function batch(BatchAttendanceRequest $request, AttendanceSession $attendanceSession, UpsertAttendanceBatch $action): RedirectResponse
    {
        $action->handle($request->user(), $attendanceSession, $request->validated('records'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Frequência registrada.']);

        return back();
    }
}
