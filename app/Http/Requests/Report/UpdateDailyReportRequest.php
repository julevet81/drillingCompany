<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'depth_start'      => ['sometimes', 'numeric', 'min:0'],
            'depth_end'        => ['sometimes', 'numeric', 'gte:depth_start'],
            'fuel_consumption' => ['nullable', 'numeric', 'min:0'],
            'incidents'        => ['nullable', 'integer', 'min:0'],
            'npt_hours'        => ['nullable', 'numeric', 'min:0'],
            'npt_cause'        => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:5000'],

            'tools'                    => ['nullable', 'array'],
            'tools.*.drilling_tool_id' => ['required', 'exists:drilling_tools,id'],
            'tools.*.quantity_used'    => ['required', 'integer', 'min:0'],
            'tools.*.total_length'     => ['required', 'numeric', 'min:0'],

            'equipments'                => ['nullable', 'array'],
            'equipments.*.equipment_id' => ['required', 'exists:equipments,id'],
            'equipments.*.status'       => ['nullable', 'in:Operational,Maintenance,Out_of_Service'],

            // تعديل موظفي الـ shifts الموجودة
            'shifts'                          => ['nullable', 'array', 'max:2'],
            'shifts.*.periode'                => ['required', 'in:day,night'],
            'shifts.*.employees'              => ['nullable', 'array'],
            'shifts.*.employees.*.employee_id'=> ['required', 'exists:employees,id'],
            'shifts.*.employees.*.function'   => ['nullable', 'string', 'max:100'],
            'shifts.*.employees.*.status'     => ['nullable', 'in:onsite,onBase,onLeave'],
            'shifts.*.mud'              => ['nullable', 'array'],
            'shifts.*.mud.density'      => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
            'shifts.*.mud.viscosity'    => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
            'shifts.*.mud.ph'           => ['required_with:shifts.*.mud', 'numeric', 'min:0', 'max:14'],
            'shifts.*.mud.filtra'       => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
        ];
    }
}
