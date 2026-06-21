<?php

namespace App\Http\Requests\Rig;

use App\Models\Rig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRigRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rig  = $this->route('rig');
        $user = $this->user();
        return $user->hasRole('Super_Admin') || $rig->manager_id === $user->id;
    }

    public function rules(): array
    {
        $rigId = $this->route('rig')->id;

        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'code'           => ['sometimes', 'string', 'max:50', Rule::unique('rigs', 'code')->ignore($rigId)],
            'photo'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'manager_id'     => ['nullable', 'exists:users,id'],
            'location_id'    => ['nullable', 'exists:locations,id'],
            'status'         => ['sometimes', Rule::in(Rig::STATUSES)],
            'current_depth'  => ['sometimes', 'numeric', 'min:0'],
            'target_depth'   => ['sometimes', 'numeric', 'min:0'],
            'drilling_phase' => ['nullable', 'string', 'max:100'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date'],
            'notes'          => ['nullable', 'text']
        ];
    }
}
