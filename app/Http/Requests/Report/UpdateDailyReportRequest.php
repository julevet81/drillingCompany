<?php

namespace App\Http\Requests\Report;

use App\Models\RigMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'depth_start'      => ['nullable', 'numeric', 'min:0'],
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
            'equipments.*.hours_used'     => ['nullable', 'numeric', 'min:0'], 

            // تعديل موظفي الـ shifts الموجودة
            'shifts'                            => ['nullable', 'array', 'max:2'],
            'shifts.*.post'                     => ['sometimes', 'in:post_1,post_2'],
            'shifts.*.start_time'               => ['required', 'date_format:H:i'],
            'shifts.*.end_time'                 => ['required', 'date_format:H:i'],
            'shifts.*.description'              => ['nullable', 'string', 'max:2000'],
            'shifts.*.lithologie'               => ['nullable', 'string', 'max:255'],
            'shifts.*.employees'                => ['nullable', 'array'],
            'shifts.*.employees.*.employee_id' => [
                'required',
                'exists:employees,id',
                function ($attribute, $value, $fail) {
                    $report = $this->route('daily_report');

                    $conflict = DB::table('employee_shifts')
                        ->join('shifts', 'shifts.id', '=', 'employee_shifts.shift_id')
                        ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
                        ->where('employee_shifts.employee_id', $value)
                        ->where('daily_reports.id', '!=', $report->id)
                        ->whereDate('daily_reports.report_date', $report->report_date)
                        ->where('daily_reports.rig_id', '!=', $report->rig_id)
                        ->select('daily_reports.rig_id')
                        ->first();

                    if ($conflict) {
                        $fail("Employee #{$value} is already assigned to rig #{$conflict->rig_id} on this date.");
                    }
                },
            ],
            'shifts.*.employees.*.function'     => ['nullable', 'string', 'max:100'],
            'shifts.*.employees.*.status'       => ['nullable', 'in:onsite,onBase,onLeave'],
            'shifts.*.mud'                      => ['nullable', 'array'],
            'shifts.*.mud.density'              => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
            'shifts.*.mud.viscosity'            => ['required_with:shifts.*.mud', 'numeric', 'min:0'],
            'shifts.*.mud.ph'                   => ['required_with:shifts.*.mud', 'numeric', 'min:0', 'max:14'],
            'shifts.*.mud.filtra'               => ['required_with:shifts.*.mud', 'numeric', 'min:0'],

            'materials.*.rig_material_id' => [
                'required',
                'exists:rig_materials,id',
                function ($attribute, $value, $fail) {
                    $report = $this->route('daily_report');
                    $belongsToRig = RigMaterial::where('id', $value)
                        ->where('rig_id', $report->rig_id)
                        ->exists();

                    if (!$belongsToRig) {
                        $fail('This material does not belong to this report\'s rig.');
                    }
                },
            ],
        ];
    }
}