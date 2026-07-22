<?php

namespace Tests\Feature\Http;

use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationalHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseThreeBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_people_index_is_scoped_and_has_inertia_contract(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith('people.view', $branch);
        $visible = Person::factory()->create(['organizational_unit_id' => $leaf, 'name' => 'Visible']);
        Person::factory()->create(['organizational_unit_id' => $sibling, 'name' => 'Hidden']);

        $this->actingAs($actor)->get(route('people.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('people/Index', false)
                ->has('people.data', 1)->where('people.data.0.id', $visible->id)
                ->has('people.links')->has('filters')->has('manageableUnitIds'));
    }

    public function test_person_mutations_audit_and_deny_sibling_or_cross_branch_scope(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith('people.manage', $branch, ['people.view']);

        $this->actingAs($actor)->post(route('people.store'), [
            'organizational_unit_id' => $leaf->id, 'name' => 'Nova Pessoa', 'email' => 'new@example.test',
        ])->assertRedirect();
        $person = Person::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'person.created', 'auditable_id' => $person->id, 'actor_user_id' => $actor->id]);

        $this->actingAs($actor)->put(route('people.update', $person), [
            'organizational_unit_id' => $sibling->id, 'name' => 'Moved', 'status' => 'active',
        ])->assertForbidden();
        $outside = Person::factory()->create(['organizational_unit_id' => $sibling]);
        $this->actingAs($actor)->patch(route('people.archive', $outside))->assertForbidden();
    }

    public function test_user_index_contract_and_sibling_user_mutations_are_denied(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith('users.manage', $branch, ['users.view']);
        $visible = User::factory()->create();
        Person::factory()->create(['organizational_unit_id' => $leaf, 'user_id' => $visible]);
        $outside = User::factory()->create();
        Person::factory()->create(['organizational_unit_id' => $sibling, 'user_id' => $outside]);

        $this->actingAs($actor)->get(route('users.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('users/Index', false)->has('users.data', 2)
                ->has('users.links')->has('filters'));
        $this->actingAs($actor)->put(route('users.update', $outside), ['name' => 'No', 'email' => 'no@example.test'])->assertForbidden();
        $this->actingAs($actor)->patch(route('users.disable', $outside), ['reason' => 'No'])->assertForbidden();
    }

    public function test_user_create_update_disable_reactivate_are_audited(): void
    {
        [, $branch, $leaf] = $this->hierarchy();
        $actor = $this->actorWith('users.manage', $branch, ['users.view']);
        $target = User::factory()->create();
        Person::factory()->create(['organizational_unit_id' => $leaf, 'user_id' => $target]);

        $this->actingAs($actor)->put(route('users.update', $target), ['name' => 'Updated', 'email' => 'updated@example.test'])->assertRedirect();
        $this->actingAs($actor)->patch(route('users.disable', $target), ['reason' => 'Licença'])->assertRedirect();
        $this->actingAs($actor)->patch(route('users.reactivate', $target))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'auditable_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled', 'auditable_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.reactivated', 'auditable_id' => $target->id]);
    }

    public function test_access_grant_index_contract_create_revoke_audit_and_cross_branch_denial(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $manage = Permission::factory()->create(['code' => 'access.manage']);
        $admin = Role::factory()->create(['hierarchy_level' => 2]);
        $admin->permissions()->attach($manage);
        $lower = Role::factory()->create(['hierarchy_level' => 3]);
        $actor = User::factory()->create();
        AccessGrant::factory()->create(['user_id' => $actor, 'role_id' => $admin, 'organizational_unit_id' => $branch]);
        $target = User::factory()->create();

        $this->actingAs($actor)->get(route('access-grants.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('access-grants/Index', false)->has('accessGrants.data', 1)->has('accessGrants.links')->has('filters'));
        $this->actingAs($actor)->post(route('access-grants.store'), ['user_id' => $target->id, 'role_id' => $lower->id, 'organizational_unit_id' => $leaf->id])->assertRedirect();
        $grant = AccessGrant::query()->where('user_id', $target->id)->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'access.granted', 'auditable_id' => $grant->id]);
        $this->actingAs($actor)->patch(route('access-grants.revoke', $grant), ['reason' => 'Encerrado'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'access.revoked', 'auditable_id' => $grant->id]);
        $this->actingAs($actor)->post(route('access-grants.store'), ['user_id' => $target->id, 'role_id' => $lower->id, 'organizational_unit_id' => $sibling->id])->assertForbidden();
    }

    private function actorWith(string $permissionCode, OrganizationalUnit $scope, array $otherPermissions = []): User
    {
        $role = Role::factory()->create(['hierarchy_level' => 2]);
        foreach ([$permissionCode, ...$otherPermissions] as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['name' => $code]);
            $role->permissions()->attach($permission);
        }
        $actor = User::factory()->create();
        AccessGrant::factory()->create(['user_id' => $actor, 'role_id' => $role, 'organizational_unit_id' => $scope]);

        return $actor;
    }

    private function hierarchy(): array
    {
        $types = collect([1, 2, 3])->map(fn ($order) => OrganizationalUnitType::factory()->create(['hierarchy_order' => $order]));
        $service = app(OrganizationalHierarchyService::class);
        $root = $service->create(['organizational_unit_type_id' => $types[0]->id, 'code' => 'ROOT', 'name' => 'Root']);
        $branch = $service->create(['organizational_unit_type_id' => $types[1]->id, 'code' => 'BRANCH', 'name' => 'Branch'], $root);
        $leaf = $service->create(['organizational_unit_type_id' => $types[2]->id, 'code' => 'LEAF', 'name' => 'Leaf'], $branch);
        $sibling = $service->create(['organizational_unit_type_id' => $types[1]->id, 'code' => 'SIBLING', 'name' => 'Sibling'], $root);

        return [$root, $branch, $leaf, $sibling];
    }
}
