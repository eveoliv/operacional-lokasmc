<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\Registration;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class RegistrationPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::RegistrationsView)->isNotEmpty();
    }

    public function view(User $user, Registration $registration): bool
    {
        return $this->authorizer->allows($user, PermissionCode::RegistrationsView, $registration->event->organizational_unit_id);
    }

    public function cancel(User $user, Registration $registration): bool
    {
        return $this->authorizer->allows($user, PermissionCode::RegistrationsManage, $registration->event->organizational_unit_id);
    }
}
