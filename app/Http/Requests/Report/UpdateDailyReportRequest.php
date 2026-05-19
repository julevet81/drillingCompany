<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'depth_start'      => ['sometimes', 'numeric', 'min:0'],
            'depth_end'        => ['sometimes', 'numeric', 'gte:depth_start'],
            'workers_count'    => ['nullable', 'integer', 'min:0'],
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
            'equipments.*.status'       => ['required', 'in:Operational,Maintenance,Out_of_Service'],
        ];
    }
}
