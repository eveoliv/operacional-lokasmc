<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('organizational_unit');

        return $unit instanceof OrganizationalUnit && $this->user()->can('update', $unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unit = $this->route('organizational_unit');

        return [
            'organizational_unit_type_id' => ['required', 'integer', Rule::exists('organizational_unit_types', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', Rule::unique('organizational_units', 'code')->ignore($unit)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
