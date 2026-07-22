<?php

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\Event;
use App\Models\User;
use App\Services\ScopeAuthorizer;

class EventPolicy
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::EventsView)->isNotEmpty();
    }

    public function view(User $user, Event $event): bool
    {
        return $this->authorizer->allows($user, PermissionCode::EventsView, $event->organizational_unit_id);
    }

    public function create(User $user): bool
    {
        return $this->authorizer->accessIds($user, PermissionCode::EventsManage)->isNotEmpty();
    }

    public function update(User $user, Event $event): bool
    {
        return $this->authorizer->allows($user, PermissionCode::EventsManage, $event->organizational_unit_id);
    }

    public function transition(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function manageRegistrations(User $user, Event $event): bool
    {
        return $this->authorizer->allows($user, PermissionCode::RegistrationsManage, $event->organizational_unit_id);
    }
}
