<?php

namespace Tests\Feature\Http;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationalHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationAndAuditBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_organization_index_is_scoped_and_exposes_inertia_contract(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith(['organization.view'], $branch);

        $this->actingAs($actor)->get(route('organization.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('organization/Index', false)
                ->has('units', 2)
                ->where('units.0.id', $branch->id)
                ->where('units.1.id', $leaf->id)
                ->has('types', 3)->has('filters')->has('manageableUnitIds', 0));

        $this->assertNotEquals($sibling->id, $leaf->id);
    }

    public function test_organization_mutations_use_hierarchy_service_audit_and_scope(): void
    {
        [$root, $branch, , $sibling] = $this->hierarchy();
        $actor = $this->actorWith(['organization.manage'], $root);
        $type = OrganizationalUnitType::query()->where('hierarchy_order', 3)->firstOrFail();

        $this->actingAs($actor)->post(route('organization.store'), [
            'organizational_unit_type_id' => $type->id,
            'parent_id' => $branch->id,
            'code' => 'NEW',
            'name' => 'Nova',
        ])->assertRedirect();

        $unit = OrganizationalUnit::query()->where('code', 'NEW')->firstOrFail();
        $this->assertDatabaseHas('organizational_unit_closure', ['ancestor_id' => $root->id, 'descendant_id' => $unit->id, 'depth' => 2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.created', 'auditable_id' => $unit->id]);

        $this->actingAs($actor)->patch(route('organization.move', $unit), ['parent_id' => $sibling->id])->assertRedirect();
        $this->assertDatabaseHas('organizational_unit_closure', ['ancestor_id' => $sibling->id, 'descendant_id' => $unit->id, 'depth' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.moved', 'auditable_id' => $unit->id]);

        $outsideActor = $this->actorWith(['organization.manage'], $branch);
        $this->actingAs($outsideActor)->patch(route('organization.archive', $sibling))->assertForbidden();
    }

    public function test_audit_index_is_read_only_scoped_and_has_inertia_contract(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith(['audit.view'], $branch);
        $visible = AuditLog::factory()->create(['organizational_unit_id' => $leaf, 'action' => 'visible.action']);
        AuditLog::factory()->create(['organizational_unit_id' => $sibling, 'action' => 'hidden.action']);

        $this->actingAs($actor)->get(route('audit.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('audit/Index', false)
                ->has('logs.data', 1)->where('logs.data.0.id', $visible->id)
                ->has('logs.links')->where('actions.0', 'visible.action')->has('filters'));

        $this->actingAs(User::factory()->create())->get(route('audit.index'))->assertForbidden();
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutesByName())->keys()->contains(fn (string $name) => str_starts_with($name, 'audit.') && $name !== 'audit.index'));
    }

    public function test_shared_capabilities_are_effective_permission_codes(): void
    {
        [, $branch] = $this->hierarchy();
        $actor = $this->actorWith(['organization.view', 'audit.view'], $branch);

        $this->actingAs($actor)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.capabilities', ['organization.view', 'audit.view']));
    }

    /** @param list<string> $permissionCodes */
    private function actorWith(array $permissionCodes, OrganizationalUnit $scope): User
    {
        $role = Role::factory()->create();
        foreach ($permissionCodes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['name' => $code]);
            $role->permissions()->attach($permission);
        }
        $actor = User::factory()->create();
        AccessGrant::factory()->create(['user_id' => $actor, 'role_id' => $role, 'organizational_unit_id' => $scope]);

        return $actor;
    }

    /** @return array{OrganizationalUnit, OrganizationalUnit, OrganizationalUnit, OrganizationalUnit} */
    private function hierarchy(): array
    {
        $types = collect([1, 2, 3])->map(fn (int $order) => OrganizationalUnitType::factory()->create(['hierarchy_order' => $order]));
        $service = app(OrganizationalHierarchyService::class);
        $root = $service->create(['organizational_unit_type_id' => $types[0]->id, 'code' => 'ROOT', 'name' => 'Root']);
        $branch = $service->create(['organizational_unit_type_id' => $types[1]->id, 'code' => 'BRANCH', 'name' => 'Branch'], $root);
        $leaf = $service->create(['organizational_unit_type_id' => $types[2]->id, 'code' => 'LEAF', 'name' => 'Leaf'], $branch);
        $sibling = $service->create(['organizational_unit_type_id' => $types[1]->id, 'code' => 'SIBLING', 'name' => 'Sibling'], $root);

        return [$root, $branch, $leaf, $sibling];
    }
}
