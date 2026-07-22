<?php

namespace App\Http\Requests\Registrations;

use App\Enums\RegistrationSource;
use App\Models\Event;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $this->user()->can('manageRegistrations', $event);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', Rule::exists(Person::class, 'id')],
            'source' => ['sometimes', Rule::enum(RegistrationSource::class)],
        ];
    }
}
