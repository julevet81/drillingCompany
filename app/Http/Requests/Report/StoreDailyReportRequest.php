<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'rig_id'      => ['required', 'exists:rigs,id'],
            'report_date' => [
                'required',
                'date',
                Rule::unique('daily_reports')->where(
                    fn ($q) => $q->where('rig_id', $this->rig_id)
                ),
            ],
            'depth_start'      => ['required', 'numeric', 'min:0'],
            'depth_end'        => ['required', 'numeric', 'gte:depth_start'],
            'workers_count'    => ['nullable', 'integer', 'min:0'],
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

            // Equipment status
            'equipments'                => ['nullable', 'array'],
            'equipments.*.equipment_id' => ['required', 'exists:equipments,id'],
            'equipments.*.status'       => ['nullable', 'in:Operational,Maintenance,Out_of_Service'],

            // Employee attendance
            'shifts'               => ['nullable', 'array'],
            'shifts.*.shift_id'    => ['required', 'exists:shifts,id'],
            'shifts.*.present'     => ['boolean'],

            // Materials consumed / added
            'materials'                       => ['nullable', 'array'],
            'materials.*.rig_material_id'     => ['required', 'exists:rig_materials,id'],
            'materials.*.consumed'            => ['nullable', 'numeric', 'min:0'],
            'materials.*.added'               => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'report_date.unique' => 'A report for this rig and date already exists.',
            'depth_end.gte'      => 'End depth must be ≥ start depth.',
        ];
    }
}
