<?php

namespace App\Http\Requests\People;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;

class ArchivePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = $this->route('person');

        return $person instanceof Person && $this->user()->can('archive', $person);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
