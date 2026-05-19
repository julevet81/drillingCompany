<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->isSuperAdmin(); }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($userId)],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
