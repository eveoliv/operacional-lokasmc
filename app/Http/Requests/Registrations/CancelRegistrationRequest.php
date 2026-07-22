<?php

namespace App\Http\Requests\Registrations;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;

class CancelRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $registration = $this->route('registration');

        return $event instanceof Event && $registration instanceof Registration
            && $registration->event_id === $event->getKey()
            && $this->user()->can('cancel', $registration);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}
