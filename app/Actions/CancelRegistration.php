<?php

namespace App\Actions;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelRegistration
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Event $event, Registration $registration, User $actor, ?string $reason = null): Registration
    {
        return DB::transaction(function () use ($event, $registration, $actor, $reason): Registration {
            Event::query()->lockForUpdate()->whereKey($event->getKey())->firstOrFail();
            $registration = Registration::query()->lockForUpdate()->whereKey($registration->getKey())->firstOrFail();

            if ($registration->event_id !== $event->getKey()) {
                throw ValidationException::withMessages(['registration' => 'A inscrição não pertence a este evento.']);
            }
            if ($registration->status !== RegistrationStatus::Active) {
                throw ValidationException::withMessages(['registration' => 'A inscrição já está cancelada.']);
            }

            $old = $registration->only(['status', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason']);
            $registration->forceFill([
                'status' => RegistrationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();
            $this->audit->log('registration.cancelled', $registration, $actor, $event->organizationalUnit, $old, $registration->only(array_keys($old)));

            return $registration;
        });
    }
}
