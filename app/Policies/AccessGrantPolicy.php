<?php

namespace App\Policies;

use App\Models\AccessGrant;
use App\Models\User;
use App\Services\ScopeAuthorizer;
use Illuminate\Auth\Access\Response;

class AccessGrantPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, 'access.manage')->isNotEmpty();
    }

    public function view(User $user, AccessGrant $grant): bool
    {
        return $this->authorizer->allows($user, 'access.manage', $grant->organizational_unit_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function revoke(User $user, AccessGrant $grant): Response
    {
        if ($user->getKey() === $grant->user_id) {
            return Response::deny('Você não pode revogar o próprio acesso.');
        }

        return $this->authorizer->allows($user, 'access.manage', $grant->organizational_unit_id)
            ? Response::allow()
            : Response::deny();
    }
}
