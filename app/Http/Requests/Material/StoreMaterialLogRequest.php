<?php

namespace App\Http\Requests\Material;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialLogRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'rig_material_id' => ['required', 'exists:rig_materials,id'],
            'log_date'        => ['nullable', 'date', 'before_or_equal:today'],
            'consumed'        => ['nullable', 'numeric', 'min:0'],
            'added'           => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $consumed = $this->consumed ?? 0;
            $added    = $this->added    ?? 0;

            if ($consumed == 0 && $added == 0) {
                $v->errors()->add('consumed', 'At least one of consumed or added must be provided.');
            }
        });
    }
}
