<?php

namespace App\Actions;

use App\Enums\PermissionCode;
use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScopeAuthorizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrantAccess
{
    public function __construct(
        private readonly ScopeAuthorizer $authorizer,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(
        User $actor,
        User $user,
        Role $role,
        OrganizationalUnit $scope,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
        ?AccessGrant $delegatedFrom = null,
    ): AccessGrant {
        if ($actor->is($user)) {
            $this->fail('user_id', 'Você não pode alterar o próprio acesso.');
        }

        if ($actor->disabled_at !== null || $user->disabled_at !== null || ! $role->is_active || ! $scope->is_active || $scope->archived_at !== null) {
            $this->fail('access', 'Usuário, função e unidade devem estar ativos.');
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            $this->fail('ends_at', 'O término deve ser posterior ao início.');
        }

        $source = $delegatedFrom ?? $this->delegationSources($actor, $scope, $role, $startsAt, $endsAt)->first();

        if ($source === null || ! $this->validSource($source, $actor, $scope, $role, $startsAt, $endsAt)) {
            $this->fail('role_id', 'O acesso excede a função, o escopo ou a vigência que você pode delegar.');
        }

        return DB::transaction(function () use ($actor, $user, $role, $scope, $startsAt, $endsAt, $source): AccessGrant {
            $grant = AccessGrant::query()->create([
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'organizational_unit_id' => $scope->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'granted_by_user_id' => $actor->getKey(),
                'delegated_from_grant_id' => $source->getKey(),
            ]);
            $this->audit->log('access.granted', $grant, $actor, $scope, newValues: $grant->only(['user_id', 'role_id', 'organizational_unit_id', 'starts_at', 'ends_at', 'delegated_from_grant_id']));

            return $grant;
        });
    }

    private function validSource(AccessGrant $source, User $actor, OrganizationalUnit $scope, Role $role, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt): bool
    {
        $source->loadMissing('role.permissions');

        return $source->user_id === $actor->getKey()
            && $this->authorizer->effectiveGrants($actor)->whereKey($source->getKey())->exists()
            && $source->role->permissions->contains('code', PermissionCode::AccessManage->value)
            && $source->role->hierarchy_level < $role->hierarchy_level
            && $this->authorizer->contains($source->organizational_unit_id, $scope)
            && ($source->starts_at === null || ($startsAt !== null && $startsAt->greaterThanOrEqualTo($source->starts_at)))
            && ($source->ends_at === null || ($endsAt !== null && $endsAt->lessThanOrEqualTo($source->ends_at)));
    }

    /** @return Collection<int, AccessGrant> */
    private function delegationSources(User $actor, OrganizationalUnit $scope, Role $role, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt): Collection
    {
        return $this->authorizer->effectiveGrants($actor)
            ->with('role.permissions')
            ->whereHas('role.permissions', fn ($query) => $query->where('code', PermissionCode::AccessManage->value))
            ->get()
            ->filter(fn (AccessGrant $grant): bool => $this->validSource($grant, $actor, $scope, $role, $startsAt, $endsAt))
            ->values();
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
