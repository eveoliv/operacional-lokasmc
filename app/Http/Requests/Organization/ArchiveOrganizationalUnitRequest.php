<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;

class ArchiveOrganizationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('organizational_unit');

        return $unit instanceof OrganizationalUnit && $this->user()->can('delete', $unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
