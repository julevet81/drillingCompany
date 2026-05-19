<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'full_name'   => ['required', 'string', 'max:255'],
            'photo'       => ['nullable', 'image', 'max:2048'],
            'position_id' => ['nullable', 'exists:positions,id'],
        ];
    }
}
