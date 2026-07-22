<?php

namespace Tests\Feature\Http;

use App\Enums\EventStatus;
use App\Http\Controllers\EventController;
use App\Models\AccessGrant;
use App\Models\Event;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationalHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseFourEventBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Route::middleware('web')->group(function (): void {
            Route::get('/_test/events', [EventController::class, 'index'])->name('test.events.index');
            Route::post('/_test/events', [EventController::class, 'store'])->name('test.events.store');
            Route::put('/_test/events/{event}', [EventController::class, 'update'])->name('test.events.update');
            Route::patch('/_test/events/{event}/status', [EventController::class, 'transition'])->name('test.events.transition');
        });
    }

    public function test_index_is_scoped_by_owner_and_hides_archived_events(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith('events.view', $branch);
        $visible = Event::factory()->create(['organizational_unit_id' => $leaf, 'title' => 'Visible']);
        Event::factory()->create(['organizational_unit_id' => $sibling, 'title' => 'Hidden']);
        Event::factory()->create(['organizational_unit_id' => $leaf, 'status' => EventStatus::Archived, 'archived_at' => now()]);

        $this->actingAs($actor)->get('/_test/events')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('events/Index', false)
                ->has('events.data', 1)->where('events.data.0.id', $visible->id)
                ->has('events.links')->has('filters')->has('statuses', 6)->has('manageableUnitIds'));
    }

    public function test_create_persists_consistent_audiences_and_audits_but_denies_cross_branch_owner(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith('events.manage', $branch, ['events.view']);
        $payload = $this->payload($branch, [['organizational_unit_id' => $leaf->id, 'include_descendants' => true]]);

        $this->actingAs($actor)->post('/_test/events', $payload)->assertRedirect();
        $event = Event::query()->where('title', 'Encontro')->firstOrFail();
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertDatabaseHas('event_audiences', ['event_id' => $event->id, 'organizational_unit_id' => $leaf->id, 'include_descendants' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.created', 'auditable_id' => $event->id, 'actor_user_id' => $actor->id]);

        $this->actingAs($actor)->post('/_test/events', $this->payload($sibling, [['organizational_unit_id' => $sibling->id, 'include_descendants' => false]]))->assertForbidden();
    }

    public function test_audience_must_be_unique_and_within_owner_and_actor_scope(): void
    {
        [, $branch, $leaf, $sibling] = $this->hierarchy();
        $actor = $this->actorWith('events.manage', $branch);

        $this->actingAs($actor)->post('/_test/events', $this->payload($branch, [
            ['organizational_unit_id' => $leaf->id, 'include_descendants' => false],
            ['organizational_unit_id' => $leaf->id, 'include_descendants' => true],
        ]))->assertSessionHasErrors('audiences.1.organizational_unit_id');

        $this->actingAs($actor)->post('/_test/events', $this->payload($branch, [
            ['organizational_unit_id' => $sibling->id, 'include_descendants' => false],
        ]))->assertSessionHasErrors('audiences.0.organizational_unit_id');
        $this->assertDatabaseCount('events', 0);
    }

    public function test_draft_update_replaces_audiences_and_is_audited_while_published_update_is_rejected(): void
    {
        [, $branch, $leaf] = $this->hierarchy();
        $actor = $this->actorWith('events.manage', $branch);
        $event = Event::factory()->create(['organizational_unit_id' => $branch]);
        $event->audiences()->create(['organizational_unit_id' => $branch->id, 'include_descendants' => false]);

        $this->actingAs($actor)->put('/_test/events/'.$event->id, $this->payload($branch, [
            ['organizational_unit_id' => $leaf->id, 'include_descendants' => true],
        ], 'Atualizado'))->assertRedirect();
        $this->assertDatabaseMissing('event_audiences', ['event_id' => $event->id, 'organizational_unit_id' => $branch->id]);
        $this->assertDatabaseHas('event_audiences', ['event_id' => $event->id, 'organizational_unit_id' => $leaf->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.updated', 'auditable_id' => $event->id]);

        $event->update(['status' => EventStatus::Published]);
        $this->actingAs($actor)->put('/_test/events/'.$event->id, $this->payload($branch, [
            ['organizational_unit_id' => $leaf->id, 'include_descendants' => false],
        ], 'Negado'))->assertSessionHasErrors('event');
        $this->assertSame('Atualizado', $event->refresh()->title);
    }

    public function test_valid_status_chain_is_audited_and_archive_timestamp_is_consistent(): void
    {
        [, $branch] = $this->hierarchy();
        $actor = $this->actorWith('events.manage', $branch);
        $event = Event::factory()->create(['organizational_unit_id' => $branch]);
        $event->audiences()->create(['organizational_unit_id' => $branch->id, 'include_descendants' => true]);

        foreach ([EventStatus::Published, EventStatus::InProgress, EventStatus::Completed, EventStatus::Archived] as $status) {
            $this->actingAs($actor)->patch('/_test/events/'.$event->id.'/status', ['status' => $status->value])->assertRedirect();
            $this->assertSame($status, $event->refresh()->status);
            $this->assertDatabaseHas('audit_logs', ['action' => 'event.'.$status->value, 'auditable_id' => $event->id]);
        }
        $this->assertNotNull($event->archived_at);
    }

    public function test_invalid_transition_and_sibling_transition_do_not_mutate_or_audit(): void
    {
        [, $branch, , $sibling] = $this->hierarchy();
        $actor = $this->actorWith('events.manage', $branch);
        $event = Event::factory()->create(['organizational_unit_id' => $branch]);
        $event->audiences()->create(['organizational_unit_id' => $branch->id]);

        $this->actingAs($actor)->patch('/_test/events/'.$event->id.'/status', ['status' => EventStatus::Completed->value])->assertSessionHasErrors('status');
        $this->assertSame(EventStatus::Draft, $event->refresh()->status);
        $outside = Event::factory()->create(['organizational_unit_id' => $sibling]);
        $this->actingAs($actor)->patch('/_test/events/'.$outside->id.'/status', ['status' => EventStatus::Published->value])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function payload(OrganizationalUnit $owner, array $audiences, string $title = 'Encontro'): array
    {
        return ['organizational_unit_id' => $owner->id, 'title' => $title, 'starts_at' => now()->addDay()->toISOString(),
            'ends_at' => now()->addDay()->addHours(2)->toISOString(), 'capacity' => 20, 'audiences' => $audiences];
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
