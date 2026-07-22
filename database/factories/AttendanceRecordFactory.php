<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceRecord> */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'registration_id' => Registration::factory(),
            'status' => AttendanceStatus::Present->value,
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'notes' => fake()->optional()->sentence(),
            'operated_by_user_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AttendanceRecord $record): void {
            if (! $record->attendanceSession || ! $record->registration) {
                return;
            }

            $session = $record->attendanceSession;
            $registration = $record->registration;
            $audience = $session->event->audiences()->firstOrCreate([
                'organizational_unit_id' => $registration->person->organizational_unit_id,
            ], ['include_descendants' => false]);
            $registration->forceFill([
                'event_id' => $session->event_id,
                'eligible_event_audience_id' => $audience->getKey(),
                'eligible_organizational_unit_id' => $registration->person->organizational_unit_id,
            ])->save();
        });
    }
}
