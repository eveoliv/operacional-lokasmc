<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionEventStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Event $event, EventStatus $target): Event
    {
        return DB::transaction(function () use ($actor, $event, $target): Event {
            $locked = Event::query()->with(['organizationalUnit', 'audiences'])->lockForUpdate()->whereKey($event->getKey())->firstOrFail();
            $current = $locked->status;
            if (! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => "A transição de {$current->value} para {$target->value} não é permitida."]);
            }
            if ($target === EventStatus::Published && $locked->audiences->isEmpty()) {
                throw ValidationException::withMessages(['audiences' => 'O evento precisa ter ao menos um público antes da publicação.']);
            }

            $locked->forceFill([
                'status' => $target,
                'archived_at' => $target === EventStatus::Archived ? now() : null,
            ])->save();
            $this->audit->log('event.'.$target->value, $locked, $actor, $locked->organizationalUnit,
                oldValues: ['status' => $current->value, 'archived_at' => null],
                newValues: ['status' => $target->value, 'archived_at' => $locked->archived_at]);

            return $locked->refresh();
        });
    }
}
