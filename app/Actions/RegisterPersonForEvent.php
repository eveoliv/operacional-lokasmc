<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Person;
use App\Models\Registration;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EventAudienceEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterPersonForEvent
{
    public function __construct(
        private readonly EventAudienceEligibility $eligibility,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Event $event, Person $person, User $actor, RegistrationSource $source = RegistrationSource::Operator): Registration
    {
        return DB::transaction(function () use ($event, $person, $actor, $source): Registration {
            $event = Event::query()->lockForUpdate()->whereKey($event->getKey())->firstOrFail();

            if ($event->status !== EventStatus::Published || $event->archived_at !== null) {
                $this->fail('event', 'Este evento não aceita inscrições.');
            }

            $audience = $this->eligibility->resolve($event, $person);
            if ($audience === null) {
                $this->fail('person_id', 'A pessoa não pertence ao público elegível deste evento.');
            }

            $registration = Registration::query()
                ->where('event_id', $event->getKey())
                ->where('person_id', $person->getKey())
                ->lockForUpdate()->first();

            if ($registration?->status === RegistrationStatus::Active) {
                $this->fail('person_id', 'A pessoa já está inscrita neste evento.');
            }

            if ($event->capacity !== null && $event->registrations()->active()->count() >= $event->capacity) {
                $this->fail('event', 'A capacidade do evento foi atingida.');
            }

            $old = $registration?->only(['status', 'source', 'operated_by_user_id', 'eligible_event_audience_id', 'eligible_organizational_unit_id', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason']);
            $registration ??= new Registration(['event_id' => $event->getKey(), 'person_id' => $person->getKey()]);
            $registration->forceFill([
                'status' => RegistrationStatus::Active,
                'source' => $source,
                'operated_by_user_id' => $actor->getKey(),
                'eligible_event_audience_id' => $audience->getKey(),
                'eligible_organizational_unit_id' => $person->organizational_unit_id,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancellation_reason' => null,
            ])->save();

            $this->audit->log($old === null ? 'registration.created' : 'registration.reinstated', $registration, $actor, $event->organizationalUnit, $old ?? [], $registration->only(['status', 'source', 'operated_by_user_id', 'eligible_event_audience_id', 'eligible_organizational_unit_id', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason']));

            return $registration;
        });
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
