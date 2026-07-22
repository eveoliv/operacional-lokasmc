<?php

namespace Tests\Feature\Services;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Services\OrganizationalHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationalHierarchyServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalHierarchyService $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(OrganizationalHierarchyService::class);
    }

    public function test_it_creates_closure_rows_for_each_ancestor(): void
    {
        [$rootType, $branchType, $leafType] = $this->types();
        $root = $this->create($rootType, 'root');
        $branch = $this->create($branchType, 'branch', $root);
        $leaf = $this->create($leafType, 'leaf', $branch);

        $this->assertClosure($root, $root, 0);
        $this->assertClosure($branch, $branch, 0);
        $this->assertClosure($leaf, $leaf, 0);
        $this->assertClosure($root, $branch, 1);
        $this->assertClosure($branch, $leaf, 1);
        $this->assertClosure($root, $leaf, 2);
        $this->assertSame(6, DB::table('organizational_unit_closure')->count());
    }

    public function test_it_moves_a_subtree_and_rebuilds_only_external_paths(): void
    {
        [$rootType, $branchType, $leafType] = $this->types();
        $oldRoot = $this->create($rootType, 'old-root');
        $newRoot = $this->create($rootType, 'new-root');
        $branch = $this->create($branchType, 'branch', $oldRoot);
        $leaf = $this->create($leafType, 'leaf', $branch);

        $this->hierarchy->move($branch, $newRoot);

        $this->assertSame($newRoot->getKey(), $branch->refresh()->parent_id);
        $this->assertDatabaseMissing('organizational_unit_closure', [
            'ancestor_id' => $oldRoot->getKey(),
            'descendant_id' => $branch->getKey(),
        ]);
        $this->assertDatabaseMissing('organizational_unit_closure', [
            'ancestor_id' => $oldRoot->getKey(),
            'descendant_id' => $leaf->getKey(),
        ]);
        $this->assertClosure($newRoot, $branch, 1);
        $this->assertClosure($newRoot, $leaf, 2);
        $this->assertClosure($branch, $leaf, 1);
    }

    public function test_it_can_move_a_subtree_to_the_root(): void
    {
        [$rootType, $branchType, $leafType] = $this->types();
        $root = $this->create($rootType, 'root');
        $branch = $this->create($branchType, 'branch', $root);
        $leaf = $this->create($leafType, 'leaf', $branch);

        $this->hierarchy->move($branch, null);

        $this->assertNull($branch->refresh()->parent_id);
        $this->assertDatabaseMissing('organizational_unit_closure', [
            'ancestor_id' => $root->getKey(),
            'descendant_id' => $branch->getKey(),
        ]);
        $this->assertClosure($branch, $leaf, 1);
    }

    public function test_it_rejects_cycles_without_changing_the_hierarchy(): void
    {
        [$rootType, $branchType, $leafType] = $this->types();
        $root = $this->create($rootType, 'root');
        $branch = $this->create($branchType, 'branch', $root);
        $leaf = $this->create($leafType, 'leaf', $branch);

        try {
            $this->hierarchy->move($root, $leaf);
            $this->fail('Expected hierarchy validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }

        $this->assertNull($root->refresh()->parent_id);
        $this->assertSame($root->getKey(), $branch->refresh()->parent_id);
        $this->assertClosure($root, $leaf, 2);
    }

    public function test_it_validates_parent_hierarchy_order_on_create_and_move(): void
    {
        [$rootType, $branchType] = $this->types();
        $root = $this->create($rootType, 'root');
        $branch = $this->create($branchType, 'branch', $root);

        $this->expectException(ValidationException::class);

        $this->create($rootType, 'invalid', $branch);
    }

    public function test_it_updates_attributes_without_corrupting_closure_rows(): void
    {
        [$rootType] = $this->types();
        $root = $this->create($rootType, 'root');

        $updated = $this->hierarchy->update($root, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $updated->name);
        $this->assertClosure($root, $root, 0);
        $this->assertSame(1, DB::table('organizational_unit_closure')->count());
    }

    public function test_it_archives_an_entire_subtree_without_removing_closure_rows(): void
    {
        [$rootType, $branchType, $leafType] = $this->types();
        $root = $this->create($rootType, 'root');
        $branch = $this->create($branchType, 'branch', $root);
        $leaf = $this->create($leafType, 'leaf', $branch);

        $this->hierarchy->archive($branch);

        $this->assertTrue($root->refresh()->is_active);
        $this->assertFalse($branch->refresh()->is_active);
        $this->assertNotNull($branch->archived_at);
        $this->assertFalse($leaf->refresh()->is_active);
        $this->assertNotNull($leaf->archived_at);
        $this->assertClosure($root, $leaf, 2);
    }

    /** @return array<int, OrganizationalUnitType> */
    private function types(): array
    {
        return [
            OrganizationalUnitType::factory()->create(['hierarchy_order' => 1]),
            OrganizationalUnitType::factory()->create(['hierarchy_order' => 2]),
            OrganizationalUnitType::factory()->create(['hierarchy_order' => 3]),
        ];
    }

    private function create(
        OrganizationalUnitType $type,
        string $code,
        ?OrganizationalUnit $parent = null,
    ): OrganizationalUnit {
        return $this->hierarchy->create([
            'organizational_unit_type_id' => $type->getKey(),
            'code' => $code,
            'name' => ucfirst($code),
        ], $parent);
    }

    private function assertClosure(
        OrganizationalUnit $ancestor,
        OrganizationalUnit $descendant,
        int $depth,
    ): void {
        $this->assertDatabaseHas('organizational_unit_closure', [
            'ancestor_id' => $ancestor->getKey(),
            'descendant_id' => $descendant->getKey(),
            'depth' => $depth,
        ]);
    }
}
