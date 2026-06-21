<?php

namespace App\Http\Requests\Report;

use App\Models\RigMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rig_id'      => ['required', 'exists:rigs,id'],
            'report_date' => [
                'required',
                'date',
                Rule::unique('daily_reports')->where(
                    fn($q) => $q->where('rig_id', $this->rig_id)
                ),
            ],
            'depth_start'      => ['required', 'numeric', 'min:0'],
            'depth_end'        => ['required', 'numeric', 'gte:depth_start'],
            'fuel_consumption' => ['nullable', 'numeric', 'min:0'],
            'incidents'        => ['nullable', 'integer', 'min:0'],
            'npt_hours'        => ['nullable', 'numeric', 'min:0'],
            'npt_cause'        => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:5000'],

            // BHA Tools
            'tools'                    => ['nullable', 'array'],
            'tools.*.drilling_tool_id' => ['required', 'exists:drilling_tools,id'],
            'tools.*.quantity_used'    => ['required', 'integer', 'min:0'],
            'tools.*.total_length'     => ['required', 'numeric', 'min:0'],

            // Equipment
            'equipments'                  => ['nullable', 'array'],
            'equipments.*.equipment_id'   => ['required', 'exists:equipments,id'],
            'equipments.*.status'         => ['nullable', 'in:Operational,Maintenance,Out_of_Service'],
            'equipments.*.hours_used'     => ['nullable', 'numeric', 'min:0'],

            // Shifts
            'shifts'                            => ['nullable', 'array', 'max:2'],
            'shifts.*.post'                     => ['required', 'in:post_1,post_2'],
            'shifts.*.start_time'               => ['required', 'date_format:H:i'],
            'shifts.*.end_time'                 => ['required', 'date_format:H:i'],
            'shifts.*.employees'                => ['nullable', 'array'],
            'shifts.*.employees.*.employee_id'  => ['required', 'exists:employees,id'],
            'shifts.*.employees.*.function'     => ['nullable', 'string', 'max:100'],
            'shifts.*.employees.*.status'       => ['nullable', 'in:onsite,onBase,onLeave'],
            'shifts.*.mud'                      => ['nullable', 'array'],
            'shifts.*.mud.density'              => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
            'shifts.*.mud.viscosity'            => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
            'shifts.*.mud.ph'                   => ['required_with:shifts.*.mud', 'numeric', 'min:0', 'max:14'],
            'shifts.*.mud.filtra'               => ['required_with:shifts.*.mud', 'numeric', 'min:0'],

            // Materials — مقيّدة بـ rig_id لهذا التقرير
            'materials'                   => ['nullable', 'array'],
            'materials.*.rig_material_id' => [
                'required',
                'exists:rig_materials,id',
                function ($attribute, $value, $fail) {
                    $belongsToRig = RigMaterial::where('id', $value)
                        ->where('rig_id', $this->rig_id)
                        ->exists();

                    if (!$belongsToRig) {
                        $fail('This material does not belong to the selected rig.');
                    }
                },
            ],
            'materials.*.consumed' => ['nullable', 'numeric', 'min:0'],
            'materials.*.added'    => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'report_date.unique'      => 'A report for this rig and date already exists.',
            'depth_end.gte'           => 'End depth must be ≥ start depth.',
            'shifts.max'              => 'A report can have at most 2 shifts (day and night).',
            'shifts.*.post.in'        => 'Shift post must be post_1 or post_2.',
        ];
    }
}