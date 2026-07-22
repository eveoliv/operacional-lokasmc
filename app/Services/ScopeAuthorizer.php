<?php

namespace App\Services;

use App\Enums\PermissionCode;
use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScopeAuthorizer
{
    public function allows(
        User $user,
        PermissionCode|string $permission,
        OrganizationalUnit|int $scope,
        ?Carbon $at = null,
    ): bool {
        if ($user->disabled_at !== null) {
            return false;
        }

        $scopeId = $scope instanceof OrganizationalUnit ? $scope->getKey() : $scope;

        return $this->effectiveGrants($user, $at)
            ->whereHas('role.permissions', fn (Builder $query) => $query->where('code', $this->permissionValue($permission)))
            ->whereExists(function ($query) use ($scopeId): void {
                $query->selectRaw('1')
                    ->from('organizational_unit_closure')
                    ->whereColumn('organizational_unit_closure.ancestor_id', 'access_grants.organizational_unit_id')
                    ->where('organizational_unit_closure.descendant_id', $scopeId);
            })
            ->exists();
    }

    /** @return Collection<int, int> */
    public function accessIds(User $user, PermissionCode|string $permission, ?Carbon $at = null): Collection
    {
        if ($user->disabled_at !== null) {
            return collect();
        }

        return DB::table('organizational_unit_closure')
            ->join('access_grants', 'access_grants.organizational_unit_id', '=', 'organizational_unit_closure.ancestor_id')
            ->join('roles', 'roles.id', '=', 'access_grants.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('organizational_units', 'organizational_units.id', '=', 'organizational_unit_closure.descendant_id')
            ->where('access_grants.user_id', $user->getKey())
            ->where('permissions.code', $this->permissionValue($permission))
            ->where('roles.is_active', true)
            ->where('organizational_units.is_active', true)
            ->whereNull('organizational_units.archived_at')
            ->whereNull('access_grants.revoked_at')
            ->where(fn ($query) => $query->whereNull('access_grants.starts_at')->orWhere('access_grants.starts_at', '<=', $at ?? now()))
            ->where(fn ($query) => $query->whereNull('access_grants.ends_at')->orWhere('access_grants.ends_at', '>', $at ?? now()))
            ->distinct()
            ->orderBy('organizational_unit_closure.descendant_id')
            ->pluck('organizational_unit_closure.descendant_id')
            ->map(static fn (mixed $id): int => (int) $id);
    }

    /** @return Builder<AccessGrant> */
    public function effectiveGrants(User $user, ?Carbon $at = null): Builder
    {
        $instant = $at ?? now();

        return AccessGrant::query()
            ->whereBelongsTo($user)
            ->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $instant))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $instant))
            ->whereHas('role', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('organizationalUnit', fn (Builder $query) => $query->where('is_active', true)->whereNull('archived_at'));
    }

    public function contains(OrganizationalUnit|int $container, OrganizationalUnit|int $scope): bool
    {
        $containerId = $container instanceof OrganizationalUnit ? $container->getKey() : $container;
        $scopeId = $scope instanceof OrganizationalUnit ? $scope->getKey() : $scope;

        return DB::table('organizational_unit_closure')
            ->where('ancestor_id', $containerId)
            ->where('descendant_id', $scopeId)
            ->exists();
    }

    private function permissionValue(PermissionCode|string $permission): string
    {
        return $permission instanceof PermissionCode ? $permission->value : $permission;
    }
}
