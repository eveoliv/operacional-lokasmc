<?php

namespace App\Http\Requests\Organization;

use App\Enums\PermissionCode;
use App\Models\OrganizationalUnit;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveOrganizationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('organizational_unit');
        $parentId = $this->integer('parent_id');

        if (! $unit instanceof OrganizationalUnit || ! $this->user()->can('update', $unit)) {
            return false;
        }

        if ($parentId === 0) {
            return true;
        }

        $parent = OrganizationalUnit::find($parentId);

        return $parent !== null && app(ScopeAuthorizer::class)->allows(
            $this->user(), PermissionCode::OrganizationManage, $parent
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('is_active', true)->whereNull('archived_at')],
        ];
    }
}
