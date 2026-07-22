<?php

namespace App\Http\Requests\Attendance;

use App\Enums\PermissionCode;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $this->user()->can('create', AttendanceSession::class)
            && app(ScopeAuthorizer::class)->allows($this->user(), PermissionCode::AttendanceManage, $event->organizational_unit_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at']];
    }
}
