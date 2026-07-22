<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Event
    {
        return DB::transaction(function () use ($actor, $data): Event {
            $audiences = Arr::pull($data, 'audiences', []);
            $event = Event::query()->create([...$data, 'status' => EventStatus::Draft]);
            $event->audiences()->createMany($audiences);
            $event->load(['organizationalUnit', 'audiences']);
            $this->audit->log('event.created', $event, $actor, $event->organizationalUnit,
                newValues: [...$event->only(['organizational_unit_id', 'title', 'description', 'status', 'starts_at', 'ends_at', 'location', 'capacity']), 'audiences' => $event->audiences->map->only(['organizational_unit_id', 'include_descendants'])->values()->all()]);

            return $event;
        });
    }
}
