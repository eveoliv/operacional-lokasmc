<?php

namespace App\Http\Requests\People;

use App\Enums\PermissionCode;
use App\Enums\PersonStatus;
use App\Models\OrganizationalUnit;
use App\Models\Person;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = OrganizationalUnit::find($this->integer('organizational_unit_id'));

        return $unit !== null && $this->user()->can('create', Person::class)
            && app(ScopeAuthorizer::class)->allows($this->user(), PermissionCode::PeopleManage, $unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('is_active', true)->whereNull('archived_at')],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:people,user_id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'status' => ['sometimes', Rule::enum(PersonStatus::class)->except(PersonStatus::Archived)],
        ];
    }
}
