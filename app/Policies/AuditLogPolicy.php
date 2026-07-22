<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class AuditLogPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::AuditView)->isNotEmpty();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $auditLog->organizational_unit_id !== null
            && $this->authorizer->allows($user, PermissionCode::AuditView, $auditLog->organizational_unit_id);
    }
}
