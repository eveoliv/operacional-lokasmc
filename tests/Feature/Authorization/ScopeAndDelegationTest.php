<?php

namespace Tests\Feature\Authorization;

use App\Actions\GrantAccess;
use App\Actions\RevokeAccess;
use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationalHierarchyService;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScopeAndDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_is_inherited_by_descendants_without_leaking_to_other_branches(): void
    {
        [$root, $branch, $leaf, $otherBranch] = $this->hierarchy();
        $permission = Permission::factory()->create(['code' => 'people.view']);
        $role = Role::factory()->create(['hierarchy_level' => 4]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        AccessGrant::factory()->create(['user_id' => $user, 'role_id' => $role, 'organizational_unit_id' => $branch]);

        $authorizer = app(ScopeAuthorizer::class);

        $this->assertTrue($authorizer->allows($user, 'people.view', $branch));
        $this->assertTrue($authorizer->allows($user, 'people.view', $leaf));
        $this->assertFalse($authorizer->allows($user, 'people.view', $root));
        $this->assertFalse($authorizer->allows($user, 'people.view', $otherBranch));
        $this->assertSame([$branch->id, $leaf->id], $authorizer->accessIds($user, 'people.view')->all());
    }

    public function test_expired_grant_does_not_cancel_valid_access_from_another_branch(): void
    {
        [, $branch, $leaf, $otherBranch] = $this->hierarchy();
        $permission = Permission::factory()->create(['code' => 'people.view']);
        $role = Role::factory()->create(['hierarchy_level' => 4]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        AccessGrant::factory()->create([
            'user_id' => $user, 'role_id' => $role, 'organizational_unit_id' => $branch,
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(),
        ]);
        AccessGrant::factory()->create(['user_id' => $user, 'role_id' => $role, 'organizational_unit_id' => $otherBranch]);

        $authorizer = app(ScopeAuthorizer::class);

        $this->assertFalse($authorizer->allows($user, 'people.view', $leaf));
        $this->assertTrue($authorizer->allows($user, 'people.view', $otherBranch));
    }

    public function test_delegation_rejects_role_scope_and_lifetime_escalation(): void
    {
        [, $branch, , $otherBranch] = $this->hierarchy();
        $manage = Permission::factory()->create(['code' => 'access.manage']);
        $admin = Role::factory()->create(['hierarchy_level' => 2]);
        $admin->permissions()->attach($manage);
        $peer = Role::factory()->create(['hierarchy_level' => 2]);
        $subordinate = Role::factory()->create(['hierarchy_level' => 3]);
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $source = AccessGrant::factory()->create([
            'user_id' => $actor, 'role_id' => $admin, 'organizational_unit_id' => $branch,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        ]);
        $action = app(GrantAccess::class);

        foreach ([
            fn () => $action->handle($actor, $target, $peer, $branch, now(), now()->addHours(2), $source),
            fn () => $action->handle($actor, $target, $subordinate, $otherBranch, now(), now()->addHours(2), $source),
            fn () => $action->handle($actor, $target, $subordinate, $branch, now(), now()->addDays(2), $source),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Delegation escalation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertDatabaseCount('access_grants', 1);
            }
        }
    }

    public function test_revocation_enforces_rank_scope_self_change_and_last_root_protection(): void
    {
        [$root, $branch, , $otherBranch] = $this->hierarchy();
        $manage = Permission::factory()->create(['code' => 'access.manage']);
        $rootRole = Role::factory()->create(['code' => 'ROOT_ADMIN', 'hierarchy_level' => 1]);
        $rootRole->permissions()->attach($manage);
        $adminRole = Role::factory()->create(['hierarchy_level' => 2]);
        $adminRole->permissions()->attach($manage);
        $lowerRole = Role::factory()->create(['hierarchy_level' => 3]);
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actorGrant = AccessGrant::factory()->create(['user_id' => $actor, 'role_id' => $adminRole, 'organizational_unit_id' => $branch]);
        $targetGrant = AccessGrant::factory()->create(['user_id' => $target, 'role_id' => $lowerRole, 'organizational_unit_id' => $branch]);
        $outsideGrant = AccessGrant::factory()->create(['user_id' => $target, 'role_id' => $lowerRole, 'organizational_unit_id' => $otherBranch]);
        $lastRoot = AccessGrant::factory()->create(['user_id' => $target, 'role_id' => $rootRole, 'organizational_unit_id' => $root]);
        $action = app(RevokeAccess::class);

        $this->assertNotNull($action->handle($actor, $targetGrant, 'fim da delegação')->revoked_at);

        foreach ([$actorGrant, $outsideGrant, $lastRoot] as $protected) {
            try {
                $action->handle($actor, $protected);
                $this->fail('Protected revocation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertNull($protected->fresh()->revoked_at);
            }
        }
    }

    /** @return array{OrganizationalUnit, OrganizationalUnit, OrganizationalUnit, OrganizationalUnit} */
    private function hierarchy(): array
    {
        $type1 = OrganizationalUnitType::factory()->create(['hierarchy_order' => 1]);
        $type2 = OrganizationalUnitType::factory()->create(['hierarchy_order' => 2]);
        $type3 = OrganizationalUnitType::factory()->create(['hierarchy_order' => 3]);
        $service = app(OrganizationalHierarchyService::class);
        $root = $service->create(['organizational_unit_type_id' => $type1->id, 'code' => fake()->unique()->lexify('ROOT-????'), 'name' => 'Root']);
        $branch = $service->create(['organizational_unit_type_id' => $type2->id, 'code' => fake()->unique()->lexify('BR-????'), 'name' => 'Branch'], $root);
        $leaf = $service->create(['organizational_unit_type_id' => $type3->id, 'code' => fake()->unique()->lexify('LEAF-????'), 'name' => 'Leaf'], $branch);
        $other = $service->create(['organizational_unit_type_id' => $type2->id, 'code' => fake()->unique()->lexify('OTHER-????'), 'name' => 'Other'], $root);

        return [$root, $branch, $leaf, $other];
    }
}
