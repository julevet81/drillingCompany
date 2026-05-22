<?php namespace App\Http\Requests\Rig;
use App\Models\Rig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRigRequest extends FormRequest {
    public function authorize(): bool {
        return (bool) $this->user()?->hasRole('Super_Admin');
    }

    public function rules(): array {
        return [
            'name'           => ['required','string','max:255'],
            'code'           => ['nullable','string','max:50','unique:rigs,code'],
            'manager_id'     => ['nullable','exists:users,id'],
            'location_id'    => ['nullable','exists:locations,id'],
            'status'         => ['nullable', Rule::in(Rig::STATUSES)],
            'current_depth'  => ['nullable','numeric','min:0'],
            'target_depth'   => ['nullable','numeric','min:0'],
            'drilling_phase' => ['nullable','string','max:100'],
            'start_date'     => ['nullable','date'],
            'end_date'       => ['nullable','date','after_or_equal:start_date'],
            'notes'           => ['nullable', 'text']
        ];
    }
}
