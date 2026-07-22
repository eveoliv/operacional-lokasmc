<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class OrganizationalUnitPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::OrganizationView)->isNotEmpty()
            || $this->authorizer->accessIds($user, PermissionCode::OrganizationManage)->isNotEmpty();
    }

    public function view(User $user, OrganizationalUnit $unit): bool
    {
        return $this->authorizer->allows($user, PermissionCode::OrganizationView, $unit)
            || $this->authorizer->allows($user, PermissionCode::OrganizationManage, $unit);
    }

    public function update(User $user, OrganizationalUnit $unit): bool
    {
        return $this->authorizer->allows($user, PermissionCode::OrganizationManage, $unit);
    }

    public function delete(User $user, OrganizationalUnit $unit): bool
    {
        return $this->update($user, $unit);
    }
}
