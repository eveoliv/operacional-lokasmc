<?php

namespace App\Http\Requests\Access;

use App\Enums\PermissionCode;
use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = OrganizationalUnit::find($this->integer('organizational_unit_id'));

        return $unit !== null && $this->user()->can('create', AccessGrant::class)
            && app(ScopeAuthorizer::class)->allows($this->user(), PermissionCode::AccessManage, $unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
