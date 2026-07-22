<?php

namespace App\Http\Controllers;

use App\Actions\SaveAttendanceSession;
use App\Enums\PermissionCode;
use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Http\Requests\Attendance\UpdateAttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSessionController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::AttendanceView);
        $sessions = AttendanceSession::query()->with('event:id,title,organizational_unit_id')->whereHas('event', fn ($q) => $q->whereIn('organizational_unit_id', $unitIds))
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))->latest('starts_at')->paginate(25)->withQueryString();

        return Inertia::render('attendance-sessions/Index', ['attendanceSessions' => $sessions, 'filters' => $request->only('archived')]);
    }

    public function store(StoreAttendanceSessionRequest $request, Event $event, SaveAttendanceSession $action): RedirectResponse
    {
        $action->handle($request->user(), $event, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sessão criada.']);

        return back();
    }

    public function update(UpdateAttendanceSessionRequest $request, AttendanceSession $attendanceSession, SaveAttendanceSession $action): RedirectResponse
    {
        $action->handle($request->user(), $attendanceSession->event, $request->validated(), $attendanceSession);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sessão atualizada.']);

        return back();
    }
}
