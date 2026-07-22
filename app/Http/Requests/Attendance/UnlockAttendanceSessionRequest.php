<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceSession;
use Illuminate\Foundation\Http\FormRequest;

class UnlockAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('attendance_session');

        return $session instanceof AttendanceSession && $this->user()->can('unlock', $session);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
