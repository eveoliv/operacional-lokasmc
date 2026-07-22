<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('attendance_session');

        return $session instanceof AttendanceSession && $this->user()->can('manageRecords', $session);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.registration_id' => ['required', 'integer', 'distinct', 'exists:registrations,id'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.checked_in_at' => ['nullable', 'date'],
            'records.*.checked_out_at' => ['nullable', 'date', 'after_or_equal:records.*.checked_in_at'],
            'records.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
