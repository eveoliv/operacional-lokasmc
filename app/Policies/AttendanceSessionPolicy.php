<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class AttendanceSessionPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::AttendanceView)->isNotEmpty();
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        return $this->authorizer->allows($user, PermissionCode::AttendanceView, $session->event->organizational_unit_id);
    }

    public function create(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::AttendanceManage)->isNotEmpty();
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        return $this->authorizer->allows($user, PermissionCode::AttendanceManage, $session->event->organizational_unit_id);
    }

    public function manageRecords(User $user, AttendanceSession $session): bool
    {
        return $this->update($user, $session);
    }

    public function lock(User $user, AttendanceSession $session): bool
    {
        return $this->authorizer->allows($user, PermissionCode::AttendanceLock, $session->event->organizational_unit_id);
    }

    public function unlock(User $user, AttendanceSession $session): bool
    {
        return $this->lock($user, $session);
    }
}
