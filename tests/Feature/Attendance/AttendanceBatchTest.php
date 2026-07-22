<?php

namespace Tests\Feature\Attendance;

use App\Actions\SetAttendanceSessionLock;
use App\Actions\UpsertAttendanceBatch;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\EventAudience;
use App\Models\OrganizationalUnitType;
use App\Models\Registration;
use App\Models\User;
use App\Services\OrganizationalHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_is_atomic_when_one_registration_is_from_another_event(): void
    {
        [$actor, $session, $valid] = $this->attendanceFixture();
        $outside = Registration::factory()->create();

        try {
            app(UpsertAttendanceBatch::class)->handle($actor, $session, [
                $this->item($valid), $this->item($outside),
            ]);
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('attendance_records', 0);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'attendance_record.created']);
        }
    }

    public function test_batch_is_idempotent_and_updates_existing_record_without_duplicates(): void
    {
        [$actor, $session, $registration] = $this->attendanceFixture();
        $action = app(UpsertAttendanceBatch::class);
        $item = $this->item($registration);
        $action->handle($actor, $session, [$item]);
        $action->handle($actor, $session, [$item]);

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $item['status'] = AttendanceStatus::Excused->value;
        $action->handle($actor, $session, [$item]);
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertSame(AttendanceStatus::Excused, AttendanceRecord::firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_record.updated']);
    }

    public function test_lock_and_unlock_are_idempotent_and_locked_session_rejects_batch(): void
    {
        [$actor, $session, $registration] = $this->attendanceFixture();
        $lock = app(SetAttendanceSessionLock::class);
        $lock->handle($actor, $session, true);
        $lockedAt = $session->fresh()->locked_at;
        $lock->handle($actor, $session, true);
        $this->assertEquals($lockedAt, $session->fresh()->locked_at);
        $this->assertDatabaseCount('audit_logs', 1);

        $this->expectException(ValidationException::class);
        try {
            app(UpsertAttendanceBatch::class)->handle($actor, $session, [$this->item($registration)]);
        } finally {
            $this->assertDatabaseCount('attendance_records', 0);
            $lock->handle($actor, $session, false);
            $this->assertNull($session->fresh()->locked_at);
            $this->assertNull($session->fresh()->locked_by_user_id);
            $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_session.unlocked']);
        }
    }

    public function test_inconsistent_audience_and_duplicate_registration_are_rejected_atomically(): void
    {
        [$actor, $session, $registration, $otherUnit] = $this->attendanceFixture();
        $registration->update(['eligible_organizational_unit_id' => $otherUnit->id]);

        foreach ([[$this->item($registration)], [$this->item($registration), $this->item($registration)]] as $items) {
            try {
                app(UpsertAttendanceBatch::class)->handle($actor, $session, $items);
                $this->fail('Expected validation failure.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('attendance_records', 0);
            }
        }
    }

    private function item(Registration $registration): array
    {
        return ['registration_id' => $registration->id, 'status' => AttendanceStatus::Present->value, 'checked_in_at' => now(), 'notes' => 'ok'];
    }

    private function attendanceFixture(): array
    {
        $type = OrganizationalUnitType::factory()->create(['hierarchy_order' => 1]);
        $service = app(OrganizationalHierarchyService::class);
        $unit = $service->create(['organizational_unit_type_id' => $type->id, 'code' => fake()->unique()->lexify('U???'), 'name' => 'Unit']);
        $other = $service->create(['organizational_unit_type_id' => $type->id, 'code' => fake()->unique()->lexify('O???'), 'name' => 'Other']);
        $event = Event::factory()->create(['organizational_unit_id' => $unit]);
        $audience = EventAudience::factory()->create(['event_id' => $event, 'organizational_unit_id' => $unit, 'include_descendants' => false]);
        $registration = Registration::factory()->create([
            'event_id' => $event,
            'eligible_event_audience_id' => $audience,
            'eligible_organizational_unit_id' => $unit,
        ]);

        return [User::factory()->create(), AttendanceSession::factory()->create(['event_id' => $event]), $registration, $other];
    }
}
