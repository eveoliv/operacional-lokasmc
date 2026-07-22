<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class UserPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::UsersView)->isNotEmpty();
    }

    public function create(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::UsersManage)->isNotEmpty();
    }

    public function view(User $actor, User $user): bool
    {
        return $this->within($actor, $user, PermissionCode::UsersView);
    }

    public function update(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $this->within($actor, $user, PermissionCode::UsersManage);
    }

    public function disable(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $user->disabled_at === null;
    }

    public function reactivate(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $user->disabled_at !== null;
    }

    private function within(User $actor, User $user, PermissionCode $permission): bool
    {
        $ids = $this->authorizer->accessIds($actor, $permission);

        return $user->person()->whereIn('organizational_unit_id', $ids)->exists()
            || $user->accessGrants()->whereIn('organizational_unit_id', $ids)->exists();
    }
}
