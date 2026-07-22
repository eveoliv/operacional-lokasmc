<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\Person;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class PersonPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::PeopleView)->isNotEmpty();
    }

    public function view(User $user, Person $person): bool
    {
        return $this->authorizer->allows($user, PermissionCode::PeopleView, $person->organizational_unit_id);
    }

    public function create(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::PeopleManage)->isNotEmpty();
    }

    public function update(User $user, Person $person): bool
    {
        return $this->authorizer->allows($user, PermissionCode::PeopleManage, $person->organizational_unit_id);
    }

    public function archive(User $user, Person $person): bool
    {
        return $this->update($user, $person);
    }
}
