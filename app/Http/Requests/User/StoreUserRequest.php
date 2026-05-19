<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->isSuperAdmin(); }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
