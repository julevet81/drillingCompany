<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('equipment')->id;

        return [
            'current_rig_id' => ['nullable', 'exists:rigs,id'],
            'name'           => ['sometimes', 'string', 'max:255'],
            'marque'         => ['nullable', 'string', 'max:100'],
            'serial_number'  => ['nullable', 'string', 'max:100', Rule::unique('equipments', 'serial_number')->ignore($id)],
        ];
    }
}
