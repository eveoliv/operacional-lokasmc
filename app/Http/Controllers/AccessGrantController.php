<?php

namespace App\Http\Controllers;

use App\Actions\GrantAccess;
use App\Actions\RevokeAccess;
use App\Enums\PermissionCode;
use App\Http\Requests\Access\RevokeAccessGrantRequest;
use App\Http\Requests\Access\StoreAccessGrantRequest;
use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessGrantController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', AccessGrant::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::AccessManage);
        $grants = AccessGrant::query()->with(['user:id,name,email', 'role:id,code,name,hierarchy_level', 'organizationalUnit:id,name'])
            ->whereIn('organizational_unit_id', $unitIds)
            ->when(! $request->boolean('revoked'), fn ($query) => $query->whereNull('revoked_at'))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('access-grants/Index', ['accessGrants' => $grants, 'filters' => $request->only('revoked')]);
    }

    public function store(StoreAccessGrantRequest $request, GrantAccess $action): RedirectResponse
    {
        $data = $request->validated();
        $user = User::query()->whereKey($data['user_id'])->firstOrFail();
        $role = Role::query()->whereKey($data['role_id'])->firstOrFail();
        $unit = OrganizationalUnit::query()->whereKey($data['organizational_unit_id'])->firstOrFail();
        $action->handle($request->user(), $user, $role, $unit, isset($data['starts_at']) ? now()->parse($data['starts_at']) : now(), isset($data['ends_at']) ? now()->parse($data['ends_at']) : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acesso concedido.']);

        return back();
    }

    public function revoke(RevokeAccessGrantRequest $request, AccessGrant $accessGrant, RevokeAccess $action): RedirectResponse
    {
        $action->handle($request->user(), $accessGrant, $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acesso revogado.']);

        return back();
    }
}
