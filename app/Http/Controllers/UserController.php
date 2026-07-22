<?php

namespace App\Http\Controllers;

use App\Enums\PermissionCode;
use App\Http\Requests\Users\DisableUserRequest;
use App\Http\Requests\Users\ReactivateUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', User::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::UsersView);

        $users = User::query()->with(['person.organizationalUnit:id,name'])
            ->where(function ($query) use ($unitIds): void {
                $query->whereHas('person', fn ($query) => $query->whereIn('organizational_unit_id', $unitIds))
                    ->orWhereHas('accessGrants', fn ($query) => $query->whereIn('organizational_unit_id', $unitIds));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->toString()).'%';
                $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('users/Index', ['users' => $users, 'filters' => $request->only('search')]);
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $user = User::query()->create($request->validated());
            $audit->log('user.created', $user, $request->user(), newValues: $user->only(['name', 'email']));
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário criado.']);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $audit): void {
            $old = $user->only(['name', 'email']);
            $user->update($request->validated());
            $audit->log('user.updated', $user, $request->user(), oldValues: $old, newValues: $user->only(['name', 'email']));
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário atualizado.']);

        return back();
    }

    public function disable(DisableUserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $audit): void {
            $user->forceFill(['disabled_at' => now(), 'disabled_reason' => $request->validated('reason')])->save();
            $audit->log('user.disabled', $user, $request->user(), oldValues: ['disabled_at' => null], newValues: $user->only(['disabled_at', 'disabled_reason']));
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário desativado.']);

        return back();
    }

    public function reactivate(ReactivateUserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $audit): void {
            $old = $user->only(['disabled_at', 'disabled_reason']);
            $user->forceFill(['disabled_at' => null, 'disabled_reason' => null])->save();
            $audit->log('user.reactivated', $user, $request->user(), oldValues: $old, newValues: ['disabled_at' => null, 'disabled_reason' => null]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário reativado.']);

        return back();
    }
}
