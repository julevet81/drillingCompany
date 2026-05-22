<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'current_rig_id' => ['nullable', 'exists:rigs,id'],
            'name'           => ['required', 'string', 'max:255'],
            'marque'         => ['nullable', 'string', 'max:100'],
            'serial_number'  => ['nullable', 'string', 'max:100', 'unique:equipments,serial_number'],
            'hours_of_operation' => ['nullable', 'numeric'],
            'status'         => ['sometimes', Rule::in(['operational', 'maintenance', 'out_of_service'])],
        ];
    }
}
