<?php

namespace App\Services;

use App\Enums\PersonStatus;
use App\Models\Event;
use App\Models\EventAudience;
use App\Models\Person;

class EventAudienceEligibility
{
    public function resolve(Event $event, Person $person): ?EventAudience
    {
        if ($person->status !== PersonStatus::Active || $person->archived_at !== null) {
            return null;
        }

        return EventAudience::query()
            ->select('event_audiences.*')
            ->join('organizational_unit_closure as eligibility_closure', function ($join) use ($person): void {
                $join->on('eligibility_closure.ancestor_id', '=', 'event_audiences.organizational_unit_id')
                    ->where('eligibility_closure.descendant_id', $person->organizational_unit_id);
            })
            ->where('event_audiences.event_id', $event->getKey())
            ->where(function ($query): void {
                $query->whereColumn('event_audiences.organizational_unit_id', 'eligibility_closure.descendant_id')
                    ->orWhere('event_audiences.include_descendants', true);
            })
            ->orderBy('eligibility_closure.depth')
            ->orderBy('event_audiences.id')
            ->first();
    }
}
