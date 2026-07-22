<?php

namespace App\Actions;

use App\Enums\PermissionCode;
use App\Models\AccessGrant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScopeAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeAccess
{
    public function __construct(
        private readonly ScopeAuthorizer $authorizer,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, AccessGrant $grant, ?string $reason = null): AccessGrant
    {
        if ($actor->getKey() === $grant->user_id) {
            $this->fail('access', 'Você não pode revogar o próprio acesso.');
        }

        if ($grant->revoked_at !== null) {
            return $grant;
        }

        $grant->loadMissing('role');
        $source = $this->authorizer->effectiveGrants($actor)
            ->with('role.permissions')
            ->whereHas('role.permissions', fn ($query) => $query->where('code', PermissionCode::AccessManage->value))
            ->get()
            ->first(fn (AccessGrant $candidate): bool => $candidate->role->hierarchy_level < $grant->role->hierarchy_level
                && $this->authorizer->contains($candidate->organizational_unit_id, $grant->organizational_unit_id));

        if ($source === null) {
            $this->fail('access', 'Você não pode revogar uma função de nível igual ou superior, nem fora do seu escopo.');
        }

        if ($grant->role->code === 'ROOT_ADMIN' && $this->activeRootCount() <= 1) {
            $this->fail('access', 'O último administrador raiz não pode ser revogado.');
        }

        return DB::transaction(function () use ($actor, $grant, $reason): AccessGrant {
            $locked = AccessGrant::query()->lockForUpdate()->whereKey($grant->getKey())->firstOrFail();

            if ($locked->revoked_at === null) {
                $locked->forceFill([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $actor->getKey(),
                    'revocation_reason' => $reason,
                ])->save();
                $this->audit->log('access.revoked', $locked, $actor, $locked->organizationalUnit, oldValues: ['revoked_at' => null], newValues: $locked->only(['revoked_at', 'revoked_by_user_id', 'revocation_reason']));
            }

            return $locked->refresh();
        });
    }

    private function activeRootCount(): int
    {
        return AccessGrant::query()
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereHas('role', fn ($query) => $query->where('code', 'ROOT_ADMIN')->where('is_active', true))
            ->distinct('user_id')
            ->count('user_id');
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
