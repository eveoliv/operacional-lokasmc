<?php

namespace App\Http\Controllers;

use App\Enums\PermissionCode;
use App\Http\Requests\Organization\ArchiveOrganizationalUnitRequest;
use App\Http\Requests\Organization\MoveOrganizationalUnitRequest;
use App\Http\Requests\Organization\StoreOrganizationalUnitRequest;
use App\Http\Requests\Organization\UpdateOrganizationalUnitRequest;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Services\AuditLogger;
use App\Services\OrganizationalHierarchyService;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', OrganizationalUnit::class);
        $viewableIds = $authorizer->accessIds($request->user(), PermissionCode::OrganizationView)
            ->merge($authorizer->accessIds($request->user(), PermissionCode::OrganizationManage))->unique()->values();
        $manageableIds = $authorizer->accessIds($request->user(), PermissionCode::OrganizationManage);

        return Inertia::render('organization/Index', [
            'units' => OrganizationalUnit::query()
                ->with(['type:id,code,name,hierarchy_order', 'parent:id,name'])
                ->whereIn('id', $viewableIds)
                ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('archived_at'))
                ->orderBy('name')->get(),
            'types' => OrganizationalUnitType::query()->where('is_active', true)
                ->orderBy('hierarchy_order')->get(['id', 'code', 'name', 'hierarchy_order']),
            'filters' => $request->only('archived'),
            'manageableUnitIds' => $manageableIds,
        ]);
    }

    public function store(StoreOrganizationalUnitRequest $request, OrganizationalHierarchyService $hierarchy, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $hierarchy, $audit): void {
            $parent = $request->integer('parent_id') > 0 ? OrganizationalUnit::findOrFail($request->integer('parent_id')) : null;
            $unit = $hierarchy->create([
                'organizational_unit_type_id' => $request->integer('organizational_unit_type_id'),
                'code' => $request->string('code')->toString(),
                'name' => $request->string('name')->toString(),
            ], $parent);
            $audit->log('organization.created', $unit, $request->user(), $unit, newValues: $unit->only(['organizational_unit_type_id', 'parent_id', 'code', 'name', 'is_active']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade criada.']);

        return back();
    }

    public function update(UpdateOrganizationalUnitRequest $request, OrganizationalUnit $organizationalUnit, OrganizationalHierarchyService $hierarchy, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $organizationalUnit, $hierarchy, $audit): void {
            $old = $organizationalUnit->only(['organizational_unit_type_id', 'code', 'name']);
            $unit = $hierarchy->update($organizationalUnit, $request->validated());
            $audit->log('organization.updated', $unit, $request->user(), $unit, $old, $unit->only(array_keys($old)));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade atualizada.']);

        return back();
    }

    public function move(MoveOrganizationalUnitRequest $request, OrganizationalUnit $organizationalUnit, OrganizationalHierarchyService $hierarchy, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $organizationalUnit, $hierarchy, $audit): void {
            $oldParentId = $organizationalUnit->parent_id;
            $parent = $request->integer('parent_id') > 0 ? OrganizationalUnit::findOrFail($request->integer('parent_id')) : null;
            $unit = $hierarchy->move($organizationalUnit, $parent);
            $audit->log('organization.moved', $unit, $request->user(), $unit, ['parent_id' => $oldParentId], ['parent_id' => $unit->parent_id]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade movida.']);

        return back();
    }

    public function archive(ArchiveOrganizationalUnitRequest $request, OrganizationalUnit $organizationalUnit, OrganizationalHierarchyService $hierarchy, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $organizationalUnit, $hierarchy, $audit): void {
            $unit = $hierarchy->archive($organizationalUnit);
            $audit->log('organization.archived', $unit, $request->user(), $unit, ['is_active' => true, 'archived_at' => null], $unit->only(['is_active', 'archived_at']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade e descendentes arquivados.']);

        return back();
    }
}
