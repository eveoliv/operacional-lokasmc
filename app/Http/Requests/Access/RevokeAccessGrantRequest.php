<?php

namespace App\Http\Requests\Access;

use App\Models\AccessGrant;
use Illuminate\Foundation\Http\FormRequest;

class RevokeAccessGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grant = $this->route('access_grant');

        return $grant instanceof AccessGrant && $this->user()->can('revoke', $grant);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}
