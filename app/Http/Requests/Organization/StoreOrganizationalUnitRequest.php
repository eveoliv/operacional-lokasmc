<?php

namespace App\Http\Requests\Organization;

use App\Enums\PermissionCode;
use App\Models\OrganizationalUnit;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parentId = $this->integer('parent_id');
        $authorizer = app(ScopeAuthorizer::class);

        return $parentId > 0
            ? (($parent = OrganizationalUnit::find($parentId)) !== null
                && $authorizer->allows($this->user(), PermissionCode::OrganizationManage, $parent))
            : $authorizer->accessIds($this->user(), PermissionCode::OrganizationManage)->isNotEmpty();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organizational_unit_type_id' => ['required', 'integer', Rule::exists('organizational_unit_types', 'id')->where('is_active', true)],
            'parent_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('is_active', true)->whereNull('archived_at')],
            'code' => ['required', 'string', 'max:50', 'unique:organizational_units,code'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
