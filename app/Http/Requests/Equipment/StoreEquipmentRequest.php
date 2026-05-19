<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
