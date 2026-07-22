<?php

namespace Tests\Feature\Registrations;

use App\Actions\CancelRegistration;
use App\Actions\RegisterPersonForEvent;
use App\Enums\PersonStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventAudience;
use App\Models\OrganizationalUnitType;
use App\Models\Person;
use App\Models\Registration;
use App\Models\User;
use App\Services\OrganizationalHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFourRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_uses_nearest_matching_audience_and_records_provenance(): void
    {
        [$root, $branch, $leaf] = $this->hierarchy();
        $event = Event::factory()->published()->create(['organizational_unit_id' => $root]);
        EventAudience::factory()->create(['event_id' => $event, 'organizational_unit_id' => $root, 'include_descendants' => true]);
        $nearest = EventAudience::factory()->create(['event_id' => $event, 'organizational_unit_id' => $branch, 'include_descendants' => true]);
        $person = Person::factory()->create(['organizational_unit_id' => $leaf]);

        $registration = app(RegisterPersonForEvent::class)->handle($event, $person, User::factory()->create());

        $this->assertSame($nearest->id, $registration->eligible_event_audience_id);
        $this->assertSame($leaf->id, $registration->eligible_organizational_unit_id);
    }

    public function test_capacity_and_active_duplicate_are_enforced_without_extra_rows(): void
    {
        [, $branch] = $this->hierarchy();
        $event = Event::factory()->published()->withCapacity(1)->create(['organizational_unit_id' => $branch]);
        EventAudience::factory()->create(['event_id' => $event, 'organizational_unit_id' => $branch]);
        $actor = User::factory()->create();
        $first = Person::factory()->create(['organizational_unit_id' => $branch]);
        $second = Person::factory()->create(['organizational_unit_id' => $branch]);
        app(RegisterPersonForEvent::class)->handle($event, $first, $actor);

        foreach ([$first, $second] as $person) {
            try {
                app(RegisterPersonForEvent::class)->handle($event, $person, $actor);
                $this->fail('Expected registration rejection.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, Registration::query()->count());
    }

    public function test_cancellation_releases_capacity_and_reinstatement_reuses_row(): void
    {
        [, $branch] = $this->hierarchy();
        $event = Event::factory()->published()->withCapacity(1)->create(['organizational_unit_id' => $branch]);
        EventAudience::factory()->create(['event_id' => $event, 'organizational_unit_id' => $branch]);
        $actor = User::factory()->create();
        $person = Person::factory()->create(['organizational_unit_id' => $branch]);
        $registration = app(RegisterPersonForEvent::class)->handle($event, $person, $actor);
        app(CancelRegistration::class)->handle($event, $registration, $actor, 'Pedido');
        $reinstated = app(RegisterPersonForEvent::class)->handle($event, $person, $actor);

        $this->assertSame($registration->id, $reinstated->id);
        $this->assertSame(RegistrationStatus::Active, $reinstated->status);
        $this->assertNull($reinstated->cancelled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.cancelled', 'auditable_id' => $registration->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.reinstated', 'auditable_id' => $registration->id]);
    }

    public function test_direct_audience_does_not_include_descendants_and_inactive_people_are_rejected(): void
    {
        [, $branch, $leaf] = $this->hierarchy();
        $event = Event::factory()->published()->create(['organizational_unit_id' => $branch]);
        EventAudience::factory()->create(['event_id' => $event, 'organizational_unit_id' => $branch, 'include_descendants' => false]);

        foreach ([
            Person::factory()->create(['organizational_unit_id' => $leaf]),
            Person::factory()->create(['organizational_unit_id' => $branch, 'status' => PersonStatus::Inactive]),
        ] as $person) {
            $this->expectEligibilityFailure($event, $person);
        }
    }

    private function expectEligibilityFailure(Event $event, Person $person): void
    {
        try {
            app(RegisterPersonForEvent::class)->handle($event, $person, User::factory()->create());
            $this->fail('Expected eligibility rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('person_id', $exception->errors());
        }
    }

    private function hierarchy(): array
    {
        $types = collect([1, 2, 3])->map(fn ($order) => OrganizationalUnitType::factory()->create(['hierarchy_order' => $order]));
        $service = app(OrganizationalHierarchyService::class);
        $root = $service->create(['organizational_unit_type_id' => $types[0]->id, 'code' => 'ROOT', 'name' => 'Root']);
        $branch = $service->create(['organizational_unit_type_id' => $types[1]->id, 'code' => 'BRANCH', 'name' => 'Branch'], $root);
        $leaf = $service->create(['organizational_unit_type_id' => $types[2]->id, 'code' => 'LEAF', 'name' => 'Leaf'], $branch);

        return [$root, $branch, $leaf];
    }
}
