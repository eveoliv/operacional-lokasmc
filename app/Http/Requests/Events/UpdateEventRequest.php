<?php

namespace App\Http\Requests\Events;

use App\Enums\EventStatus;
use App\Enums\PermissionCode;
use App\Models\Event;
use App\Models\OrganizationalUnit;
use App\Services\ScopeAuthorizer;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends StoreEventRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $unit = OrganizationalUnit::find($this->integer('organizational_unit_id'));

        return $event instanceof Event && $unit !== null && $this->user()->can('update', $event)
            && app(ScopeAuthorizer::class)->allows($this->user(), PermissionCode::EventsManage, $unit);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $event = $this->route('event');
                if ($event instanceof Event && $event->status !== EventStatus::Draft) {
                    $validator->errors()->add('event', 'Somente eventos em rascunho podem ser editados.');
                }
            },
        ];
    }
}
