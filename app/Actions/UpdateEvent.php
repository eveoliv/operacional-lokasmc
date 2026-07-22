<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Event $event, array $data): Event
    {
        return DB::transaction(function () use ($actor, $event, $data): Event {
            $locked = Event::query()->with('audiences')->lockForUpdate()->whereKey($event->getKey())->firstOrFail();
            if ($locked->status !== EventStatus::Draft) {
                throw ValidationException::withMessages(['event' => 'Somente eventos em rascunho podem ser editados.']);
            }

            $fields = ['organizational_unit_id', 'title', 'description', 'starts_at', 'ends_at', 'location', 'capacity'];
            $old = [...$locked->only($fields), 'audiences' => $locked->audiences->map->only(['organizational_unit_id', 'include_descendants'])->values()->all()];
            $audiences = Arr::pull($data, 'audiences', []);
            $locked->update($data);
            $locked->audiences()->delete();
            $locked->audiences()->createMany($audiences);
            $locked->load(['organizationalUnit', 'audiences']);
            $new = [...$locked->only($fields), 'audiences' => $locked->audiences->map->only(['organizational_unit_id', 'include_descendants'])->values()->all()];
            $this->audit->log('event.updated', $locked, $actor, $locked->organizationalUnit, $old, $new);

            return $locked;
        });
    }
}
