<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool {
        return $this->user()->hasRole('Super_Admin');
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:8'],
            'photo'     => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'image'     => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'avatar'    => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'file'      => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
