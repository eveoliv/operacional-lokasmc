<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class AttendanceRecordPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->authorizer->allows($user, PermissionCode::AttendanceView, $record->attendanceSession->event->organizational_unit_id);
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->authorizer->allows($user, PermissionCode::AttendanceManage, $record->attendanceSession->event->organizational_unit_id);
    }
}
