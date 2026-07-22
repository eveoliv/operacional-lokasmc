<?php

namespace App\Http\Requests\Events;

use App\Enums\PermissionCode;
use App\Models\Event;
use App\Models\OrganizationalUnit;
use App\Services\ScopeAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = OrganizationalUnit::find($this->integer('organizational_unit_id'));

        return $unit !== null && $this->user()->can('create', Event::class)
            && app(ScopeAuthorizer::class)->allows($this->user(), PermissionCode::EventsManage, $unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('is_active', true)->whereNull('archived_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*.organizational_unit_id' => ['required', 'integer', 'distinct', Rule::exists('organizational_units', 'id')->where('is_active', true)->whereNull('archived_at')],
            'audiences.*.include_descendants' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('organizational_unit_id') || $validator->errors()->has('audiences')) {
                return;
            }

            $authorizer = app(ScopeAuthorizer::class);
            $ownerId = $this->integer('organizational_unit_id');
            foreach ($this->input('audiences', []) as $index => $audience) {
                $audienceId = (int) ($audience['organizational_unit_id'] ?? 0);
                if (! $authorizer->contains($ownerId, $audienceId)
                    || ! $authorizer->allows($this->user(), PermissionCode::EventsManage, $audienceId)) {
                    $validator->errors()->add("audiences.$index.organizational_unit_id", 'O público deve estar dentro do escopo da unidade do evento e do seu escopo de gestão.');
                }
            }
        }];
    }
}
