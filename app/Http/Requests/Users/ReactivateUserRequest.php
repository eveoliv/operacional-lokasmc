<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReactivateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && $this->user()->can('reactivate', $user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
