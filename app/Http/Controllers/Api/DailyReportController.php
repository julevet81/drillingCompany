<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Report\StoreDailyReportRequest;
use App\Http\Requests\Report\UpdateDailyReportRequest;
use App\Models\DailyReport;
use App\Models\DailyReportEquipment;
use App\Models\DailyReportTool;
use App\Models\DrillingTool;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\MaterialLog;
use App\Models\Rig;
use App\Models\RigMaterial;
use App\Models\Shift;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DailyReportController extends BaseApiController
{
    /** GET /api/daily-reports */
    public function index(Request $request): JsonResponse
    {
        $query = DailyReport::with([
            'rig:id,name,code,status,drilling_phase,notes',
            'author:id,full_name',
            'reportEquipments.equipment:id,name,marque,serial_number,status,photo',
            'shifts.employees:id,full_name,photo,position_id',
            'shifts.employees.position:id,name',
            'shifts.mudCharacteristic',
        ])->withCount(['tools', 'reportEquipments', 'shifts']);

        if ($allowedRigIds = $request->attributes->get('allowed_rig_ids')) {
            $query->whereIn('id', $allowedRigIds);
        }

        if ($request->filled('date'))   $query->whereDate('report_date', $request->date);
        if ($request->filled('status')) $query->where('status', $request->status);

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('report_date', [$request->from, $request->to]);
        }

        if ($request->filled('rig_id')) {
            $query->where('rig_id', $request->rig_id);
        }

        $reports = $query->latest('report_date')->paginate($request->per_page ?? 15);

        $reports->getCollection()->transform(function (DailyReport $report) {
            $report->drilling_phase = $report->rig?->drilling_phase;

            $report->equipments_list = $report->reportEquipments->map(fn($re) => [
                'id'            => $re->equipment?->id,
                'name'          => $re->equipment?->name,
                'marque'        => $re->equipment?->marque,
                'serial_number' => $re->equipment?->serial_number,
                'status'        => $re->status,
                'photo_url'     => $re->equipment?->photo ? asset($re->equipment->photo) : null,
            ]);

            $report->employees_list = $report->shifts
                ->flatMap(fn($shift) => $shift->employees->map(fn($emp) => [
                    'id'         => $emp->id,
                    'name'       => $emp->full_name,
                    'position'   => $emp->position?->name,
                    'photo_url'  => $emp->photo ? asset($emp->photo) : null,
                    'function'   => $emp->pivot->function ?? null,
                    'status'     => $emp->pivot->status ?? null,
                    'shift'      => $shift->post,
                    'start_time' => $shift->start_time,
                    'end_time'   => $shift->end_time,
                ]))
                ->values();

            return $report;
        });

        return $this->paginated($reports);
    }

    /** GET /api/daily-reports/summary */
    public function summary(Request $request): JsonResponse
    {
        try {
            $date = $request->filled('date')
                ? \Carbon\Carbon::parse($request->date)->toDateString()
                : today()->toDateString();

            $data = DailyReport::whereDate('report_date', $date)
                ->selectRaw('
                    COUNT(*)                        as total_reports,
                    COALESCE(AVG(daily_progress),0) as avg_progress,
                    COALESCE(SUM(fuel_consumption),0) as total_fuel
                ')
                ->first();

            // عدد الموظفين الكلي عبر الـ shifts
            $totalPersonnel = Shift::whereHas(
                'report',
                fn($q) => $q->whereDate('report_date', $date)
            )->withCount('employees')->get()->sum('employees_count');

            $avgBha = DailyReportTool::whereHas(
                'report',
                fn($q) => $q->whereDate('report_date', $date)
            )->avg('total_length') ?? 0;

            $totalMaterials = DailyReportTool::whereHas(
                'report',
                fn($q) => $q->whereDate('report_date', $date)
            )->sum('quantity_used') ?? 0;

            return $this->success([
                'date'             => $date,
                'total_reports'    => (int) ($data->total_reports ?? 0),
                'avg_progress_m'   => round($data->avg_progress ?? 0, 2),
                'total_personnel'  => (int) $totalPersonnel,
                'total_fuel_l'     => round($data->total_fuel ?? 0, 2),
                'avg_bha_length_m' => round($avgBha, 2),
                'total_materials'  => (int) $totalMaterials,
            ]);
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            return $this->error('Invalid date format. Use YYYY-MM-DD', 422);
        } catch (\Exception $e) {
            return $this->error(
                config('app.debug') ? $e->getMessage() : 'Failed to load summary',
                500
            );
        }
    }

    /** POST /api/daily-reports */
    public function store(StoreDailyReportRequest $request): JsonResponse
    {
        $drillingPhase = $this->rigDrillingPhaseFrom($request);

        if ($request->filled('rig_status')) {
            $request->validate([
                'rig_status' => ['in:' . implode(',', Rig::STATUSES)],
            ]);
        }

        try {
            $report = DB::transaction(function () use ($request, $drillingPhase) {
                $data = $request->safe()->except(['tools', 'equipments', 'shifts', 'materials', 'rig_status', 'drilling_phase', 'rig_drilling_phase', 'rig', 'rig_notes', 'employees']);
                $data['created_by']     = $request->user()->id;
                $data['daily_progress'] = $data['depth_end'] - $data['depth_start'];
                $data['status']         = 'approved';

                $report = DailyReport::create($data);

                // BHA Tools — يستهلك من المخزون الكلي للأداة
                if ($request->filled('tools')) {
                    foreach ($request->tools as $t) {
                        $tool = DrillingTool::lockForUpdate()->findOrFail($t['drilling_tool_id']);

                        $newQty = $tool->total_quantity - ($t['quantity_used'] ?? 0);

                        if ($newQty < 0) {
                            throw new \InvalidArgumentException(
                                "Tool '{$tool->name}' stock insufficient (available: {$tool->total_quantity})."
                            );
                        }

                        $tool->update(['total_quantity' => $newQty]);

                        DailyReportTool::create([
                            'report_id'        => $report->id,
                            'drilling_tool_id' => $tool->id,
                            'quantity_used'    => $t['quantity_used'] ?? 0,
                            'total_length'     => $t['total_length'] ?? 0,
                        ]);
                    }
                }

                // Equipments
                if ($request->filled('equipments')) {
                    foreach ($request->equipments as $e) {
                        DailyReportEquipment::create([
                            'report_id'    => $report->id,
                            'equipment_id' => $e['equipment_id'],
                            'status'       => $e['status'] ?? 'Operational',
                            'hours_used'   => $e['hours_used'] ?? 0,
                        ]);

                        // ← تحديث موقع المعدة الحالي ليطابق هذا الـ rig
                        Equipment::where('id', $e['equipment_id'])
                            ->where('current_rig_id', '!=', $report->rig_id)
                            ->update(['current_rig_id' => $report->rig_id]);
                    }
                }

                // Shifts + موظفوهم — يُنشأ كل shift مع تقريره مباشرة
                if ($request->filled('shifts')) {
                    foreach ($request->shifts as $shiftData) {
                        $shift = Shift::create([
                            'report_id'  => $report->id,
                            'post'       => $shiftData['post'],
                            'start_time' => $shiftData['start_time'],
                            'end_time'   => $shiftData['end_time'],
                            'description' => $shiftData['description'] ?? null,
                            'lithologie'  => $shiftData['lithologie'] ?? null,
                        ]);

                        if (array_key_exists('employees', $shiftData)) {
                            $employees = $shiftData['employees'] ?? [];
                            $this->syncShiftEmployees($shift, $employees);

                            $this->updateEmployeesCurrentRig($employees, $report);
                        }

                        if (!empty($shiftData['mud'])) {
                            $shift->mudCharacteristic()->create([
                                'mud_density'   => $shiftData['mud']['density'],
                                'mud_viscosity' => $shiftData['mud']['viscosity'],
                                'mud_pH'        => $shiftData['mud']['ph'],
                                'mud_filtra'    => $shiftData['mud']['filtra'],
                            ]);
                        }
                    }
                }

                if ($request->has('employees')) {
                    $this->applyFlatEmployeeUpdates($report, $request->input('employees', []));
                }

                // Material logs
                if ($request->filled('materials')) {
                    foreach ($request->materials as $m) {
                        $rigMaterial = RigMaterial::lockForUpdate()->findOrFail($m['rig_material_id']);

                        $newQty = $rigMaterial->quantity
                            - ($m['consumed'] ?? 0)
                            + ($m['added'] ?? 0);

                        if ($newQty < 0) {
                            throw new \InvalidArgumentException(
                                "Material '{$rigMaterial->materialType?->name}' stock insufficient."
                            );
                        }

                        $rigMaterial->update(['quantity' => $newQty]);

                        MaterialLog::create([
                            'report_id'       => $report->id,
                            'rig_material_id' => $rigMaterial->id,
                            'log_date'        => $report->report_date,
                            'consumed'        => $m['consumed'] ?? 0,
                            'added'           => $m['added'] ?? 0,
                            'remaining'       => $newQty,
                        ]);
                    }
                }

                $rigUpdate = ['current_depth' => $data['depth_end']];
                if ($request->filled('rig_status')) {
                    $rigUpdate['status'] = $request->rig_status;
                }
                if ($drillingPhase !== null) {
                    $rigUpdate['drilling_phase'] = $drillingPhase;
                }
                if ($request->filled('rig_notes')) {
                    $rigUpdate['notes'] = $request->rig_notes;
                }

                $rig = Rig::withTrashed()->findOrFail($report->rig_id);
                $rig->forceFill($rigUpdate)->save();
                $report->unsetRelation('rig');

                return $report;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'daily_reports_rig_id_report_date_unique') ||
                str_contains($e->getMessage(), 'UNIQUE constraint failed: daily_reports.rig_id, daily_reports.report_date')) {
                return $this->error('A report for this rig and date already exists.', 422);
            }
            throw $e;
        }

        if ($request->filled('rig_status') || $drillingPhase !== null) {
            Cache::forget('dashboard:stats');
            Cache::forget('rigs:stats');
        }

        $createdReport = $report->load([
                'tools.drillingTool.toolType',
                'reportEquipments.equipment',
                'shifts.employees',
                'shifts.mudCharacteristic',
                'rig:id,name,code,status,drilling_phase,notes',
            ])->append('previous_report');

        return $this->created(
            array_merge($createdReport->toArray(), [
                'drilling_phase' => $createdReport->rig?->drilling_phase,
            ]),
            'Daily report created'
        );
    }

    /** GET /api/daily-reports/{report} */
    public function show(DailyReport $daily_report): JsonResponse
    {
        return $this->success($this->reportPayload($daily_report));
    }

    /** GET /api/daily-reports/last/{rig} */
    /** GET /api/daily-reports/last/{rig} */
    public function lastForRig(Rig $rig): JsonResponse
    {
        $report = DailyReport::where('rig_id', $rig->id)
            ->latest('report_date')
            ->with([
                'rig:id,name,code,status,drilling_phase,notes',
                'author:id,full_name',
                'tools.drillingTool.toolType:id,name',
                'reportEquipments.equipment:id,name,serial_number,status',
                'shifts.employees:id,full_name,photo,position_id',
                'shifts.employees.position:id,name',
                'shifts.mudCharacteristic',
                'materialLogs.rigMaterial.materialType:id,name,unit',
            ])
            ->first();

        if (!$report) {
            return $this->error('No reports found for this rig.', 404);
        }

        return $this->success($this->reportPayload($report));
    }

    /** PUT /api/daily-reports/{report} */
    public function update(UpdateDailyReportRequest $request, DailyReport $daily_report): JsonResponse
    {
        $drillingPhase = $this->rigDrillingPhaseFrom($request);

        if ($request->filled('rig_status')) {
            $request->validate([
                'rig_status' => ['in:' . implode(',', Rig::STATUSES)],
            ]);
        }

        DB::transaction(function () use ($request, $daily_report, $drillingPhase) {
            $data = $request->safe()->except(['tools', 'equipments', 'shifts', 'materials', 'rig_status', 'drilling_phase', 'rig_drilling_phase', 'rig', 'rig_notes', 'employees']);

            if (isset($data['depth_start'], $data['depth_end'])) {
                $data['daily_progress'] = $data['depth_end'] - $data['depth_start'];
            }

            $daily_report->update($data);

            $rigUpdate = [];
            if ($request->filled('rig_status')) {
                $rigUpdate['status'] = $request->rig_status;
            }
            if ($drillingPhase !== null) {
                $rigUpdate['drilling_phase'] = $drillingPhase;
            }
            if ($request->filled('rig_notes')) {
                $rigUpdate['notes'] = $request->rig_notes;
            }
            if (!empty($rigUpdate)) {
                $rig = Rig::withTrashed()->findOrFail($daily_report->rig_id);
                $rig->forceFill($rigUpdate)->save();
                $daily_report->unsetRelation('rig');
            }

            if ($request->filled('tools')) {
                foreach ($request->tools as $t) {
                    $tool = DrillingTool::lockForUpdate()->findOrFail($t['drilling_tool_id']);

                    // عكس التأثير القديم لو كان مسجَّلاً مسبقاً لهذا التقرير
                    $oldTool = DailyReportTool::where('report_id', $daily_report->id)
                        ->where('drilling_tool_id', $tool->id)
                        ->first();

                    $baseQty = $tool->total_quantity;
                    if ($oldTool) {
                        $baseQty += $oldTool->quantity_used;
                    }

                    $newQty = $baseQty - ($t['quantity_used'] ?? 0);

                    if ($newQty < 0) {
                        throw new \InvalidArgumentException(
                            "Tool '{$tool->name}' stock insufficient (available: {$baseQty})."
                        );
                    }

                    $tool->update(['total_quantity' => $newQty]);

                    DailyReportTool::updateOrCreate(
                        [
                            'report_id'        => $daily_report->id,
                            'drilling_tool_id' => $tool->id,
                        ],
                        [
                            'quantity_used' => $t['quantity_used'] ?? 0,
                            'total_length'  => $t['total_length'] ?? 0,
                        ]
                    );
                }
            }

            // ← مفقود سابقاً: تحديث equipments
            if ($request->filled('equipments')) {
                $daily_report->reportEquipments()->delete();
                foreach ($request->equipments as $e) {
                    DailyReportEquipment::create([
                        'report_id'    => $daily_report->id,
                        'equipment_id' => $e['equipment_id'],
                        'status'       => $e['status'] ?? 'Operational',
                        'hours_used'   => $e['hours_used'] ?? 0,
                    ]);
                }
            }

            // تحديث/إنشاء shifts وموظفيهم
            if ($request->filled('shifts')) {
                foreach ($request->shifts as $shiftData) {
                    $shift = !empty($shiftData['id'])
                        ? $daily_report->shifts()->whereKey($shiftData['id'])->firstOrFail()
                        : $daily_report->shifts()->firstOrNew(['post' => $shiftData['post']]);

                    $shift->fill(array_filter([
                        'post'        => $shiftData['post'] ?? $shift->post,
                        'start_time'  => $shiftData['start_time'] ?? null,
                        'end_time'    => $shiftData['end_time'] ?? null,
                        'description' => $shiftData['description'] ?? null,
                        'lithologie'  => $shiftData['lithologie'] ?? null,
                    ], fn($value) => $value !== null));
                    $shift->report_id = $daily_report->id;
                    $shift->save();

                        if (array_key_exists('employees', $shiftData)) {
                            $employees = $shiftData['employees'] ?? [];
                            $this->syncShiftEmployees($shift, $employees);

                            $this->updateEmployeesCurrentRig($employees, $daily_report);
                        }

                    if (!empty($shiftData['mud'])) {
                        $shift->mudCharacteristic()->updateOrCreate(
                            ['shift_id' => $shift->id],
                            [
                                'mud_density'   => $shiftData['mud']['density'],
                                'mud_viscosity' => $shiftData['mud']['viscosity'],
                                'mud_pH'        => $shiftData['mud']['ph'],
                                'mud_filtra'    => $shiftData['mud']['filtra'],
                            ]
                        );
                    }
                }
            }

            if ($request->has('employees')) {
                $this->applyFlatEmployeeUpdates($daily_report, $request->input('employees', []));
            }

            // ← مفقود أيضاً: مواد المخزون لم تُعالج
            if ($request->filled('materials')) {
                foreach ($request->materials as $m) {
                    $rigMaterial = RigMaterial::lockForUpdate()->findOrFail($m['rig_material_id']);

                    // ← الخطوة 1: جلب اللوغ القديم لهذا rig_material في هذا التقرير (لو موجود)
                    $oldLog = MaterialLog::where('report_id', $daily_report->id)
                        ->where('rig_material_id', $rigMaterial->id)
                        ->first();

                    // ← الخطوة 2: عكس التأثير القديم على الكمية الحالية
                    $baseQty = $rigMaterial->quantity;
                    if ($oldLog) {
                        $baseQty = $baseQty + $oldLog->consumed - $oldLog->added;
                    }

                    // ← الخطوة 3: تطبيق التأثير الجديد على القيمة المُعادة
                    $newQty = $baseQty
                        - ($m['consumed'] ?? 0)
                        + ($m['added'] ?? 0);

                    if ($newQty < 0) {
                        throw new \InvalidArgumentException(
                            "Material '{$rigMaterial->materialType?->name}' stock insufficient."
                        );
                    }

                    $rigMaterial->update(['quantity' => $newQty]);

                    MaterialLog::updateOrCreate(
                        [
                            'report_id'       => $daily_report->id,
                            'rig_material_id' => $rigMaterial->id,
                        ],
                        [
                            'log_date'  => $daily_report->report_date,
                            'consumed'  => $m['consumed'] ?? 0,
                            'added'     => $m['added'] ?? 0,
                            'remaining' => $newQty,
                        ]
                    );
                }
            }
        });

        if ($request->filled('rig_status') || $drillingPhase !== null) {
            Cache::forget('dashboard:stats');
            Cache::forget('rigs:stats');
        }

        return $this->success(
            $this->reportPayload($daily_report->fresh()),
            'Report updated'
        );
    }

    /** DELETE /api/daily-reports/{report} */
    public function destroy(DailyReport $report): JsonResponse
    {
        if ($report->status === 'approved') {
            return $this->error('Cannot delete an approved report', 422);
        }
        $report->delete();
        return $this->success(null, 'Report deleted');
    }

    /** PATCH /api/daily-reports/{report}/submit */
    public function submit(DailyReport $report): JsonResponse
    {
        if ($report->status !== 'draft') {
            return $this->error('Only draft reports can be submitted', 422);
        }
        $report->update(['status' => 'submitted']);
        return $this->success($report->only(['id', 'status']), 'Report submitted');
    }

    /** PATCH /api/daily-reports/{report}/approve */
    public function approve(DailyReport $report, Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('Super_Admin')) {
            return $this->forbidden('Only admins can approve reports');
        }
        if ($report->status !== 'submitted') {
            return $this->error('Only submitted reports can be approved', 422);
        }
        $report->update(['status' => 'approved']);
        return $this->success($report->only(['id', 'status']), 'Report approved');
    }

    private function updateEmployeesCurrentRig(array $employees, DailyReport $report): void
    {
        $employeeIds = collect($employees)
            ->map(fn($employee) => $employee['employee_id'] ?? $employee['id'] ?? null)
            ->filter()
            ->unique();

        foreach ($employeeIds as $empId) {
            $latestReportDate = DB::table('employee_shifts')
                ->join('shifts', 'shifts.id', '=', 'employee_shifts.shift_id')
                ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
                ->where('employee_shifts.employee_id', $empId)
                ->max('daily_reports.report_date');

            if (!$latestReportDate || $report->report_date->greaterThanOrEqualTo($latestReportDate)) {
                Employee::where('id', $empId)->update(['rig_id' => $report->rig_id]);
            }
        }
    }

    private function syncShiftEmployees(Shift $shift, array $employees): void
    {
        DB::table('employee_shifts')->where('shift_id', $shift->id)->delete();

        $rows = collect($employees)
            ->map(function ($employee) use ($shift) {
                $employeeId = $employee['employee_id'] ?? $employee['id'] ?? null;

                if (!$employeeId) {
                    return null;
                }

                return [
                    'shift_id'    => $shift->id,
                    'employee_id' => $employeeId,
                    'function'    => $employee['function'] ?? null,
                    'status'      => $employee['status'] ?? 'onsite',
                ];
            })
            ->filter()
            ->unique(fn($row) => $row['shift_id'] . ':' . $row['employee_id'])
            ->values()
            ->all();

        if (!empty($rows)) {
            DB::table('employee_shifts')->insert($rows);
        }

        $shift->unsetRelation('employees');
    }

    private function applyFlatEmployeeUpdates(DailyReport $report, array $employees): void
    {
        if (empty($employees)) {
            DB::table('employee_shifts')
                ->whereIn('shift_id', $report->shifts()->pluck('id'))
                ->delete();

            $report->unsetRelation('shifts');
            return;
        }

        collect($employees)
            ->filter(fn($employee) => !empty($employee['shift_id']))
            ->groupBy('shift_id')
            ->each(function ($shiftEmployees, $shiftId) use ($report) {
                $shift = $report->shifts()->whereKey($shiftId)->firstOrFail();
                $this->syncShiftEmployees($shift, $shiftEmployees->toArray());
                $this->updateEmployeesCurrentRig($shiftEmployees->toArray(), $report);
            });

        collect($employees)
            ->filter(fn($employee) => empty($employee['shift_id']) && !empty($employee['shift']))
            ->groupBy('shift')
            ->each(function ($shiftEmployees, $post) use ($report) {
                if (!in_array($post, ['post_1', 'post_2'], true)) {
                    return;
                }

                $shift = $report->shifts()->firstOrCreate(
                    ['post' => $post],
                    [
                        'start_time' => $post === 'post_1' ? '08:00' : '20:00',
                        'end_time'   => $post === 'post_1' ? '20:00' : '08:00',
                    ]
                );

                $this->syncShiftEmployees($shift, $shiftEmployees->toArray());
                $this->updateEmployeesCurrentRig($shiftEmployees->toArray(), $report);
            });

        $report->unsetRelation('shifts');
    }

    private function reportPayload(DailyReport $report): array
    {
        $report->load([
            'rig:id,name,code,location_id,status,drilling_phase,notes',
            'rig.location:id,name',
            'author:id,full_name',
            'tools.drillingTool.toolType:id,name',
            'reportEquipments.equipment:id,name,serial_number,status',
            'shifts.employees:id,full_name,photo,position_id',
            'shifts.employees.position:id,name',
            'shifts.mudCharacteristic',
            'materialLogs.rigMaterial.materialType:id,name,unit',
        ]);

        $employees = $report->shifts
            ->flatMap(fn($shift) => $shift->employees->map(fn($employee) => [
                'id'         => $employee->id,
                'name'       => $employee->full_name,
                'position'   => $employee->position?->name,
                'photo_url'  => $employee->photo ? asset($employee->photo) : null,
                'function'   => $employee->pivot->function ?? null,
                'status'     => $employee->pivot->status ?? null,
                'shift_id'   => $shift->id,
                'shift'      => $shift->post,
                'start_time' => $shift->start_time,
                'end_time'   => $shift->end_time,
            ]))
            ->values();

        return array_merge($report->toArray(), [
            'drilling_phase'   => $report->rig?->drilling_phase,
            'total_bha_length' => $report->total_bha_length,
            'workers_count'    => $employees->count(),
            'employees'        => $employees,
        ]);
    }

    private function rigDrillingPhaseFrom(Request $request): ?string
    {
        foreach (['drilling_phase', 'rig_drilling_phase', 'rig.drilling_phase'] as $key) {
            if ($request->filled($key)) {
                return $request->input($key);
            }
        }

        return null;
    }

    /** GET /api/daily-reports/by-rig/{rig} */
    public function forRig(Rig $rig, Request $request): JsonResponse
    {
        $allowedRigIds = $request->attributes->get('allowed_rig_ids');
        if ($allowedRigIds !== null && !$allowedRigIds->contains($rig->id)) {
            return $this->forbidden('You are not authorized to view reports for this rig');
        }

        $query = DailyReport::where('rig_id', $rig->id)
            ->with([
                'rig:id,name,code,status,drilling_phase,notes',
                'author:id,full_name',
                'reportEquipments.equipment:id,name,marque,serial_number,status,photo',
                'shifts.employees:id,full_name,photo,position_id',
                'shifts.employees.position:id,name',
                'shifts.mudCharacteristic',
            ])
            ->withCount(['tools', 'reportEquipments', 'shifts']);

        if ($request->filled('date'))   $query->whereDate('report_date', $request->date);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('report_date', [$request->from, $request->to]);
        }

        $reports = $query->latest('report_date')->paginate($request->per_page ?? 15);

        // نفس الـ transform الموجود في index (equipments_list, employees_list)
        $reports->getCollection()->transform(function (DailyReport $report) {
            $report->drilling_phase = $report->rig?->drilling_phase;

            $report->equipments_list = $report->reportEquipments->map(fn($re) => [
                'id'            => $re->equipment?->id,
                'name'          => $re->equipment?->name,
                'marque'        => $re->equipment?->marque,
                'serial_number' => $re->equipment?->serial_number,
                'status'        => $re->status,
                'photo_url'     => $re->equipment?->photo ? asset($re->equipment->photo) : null,
            ]);

            $report->employees_list = $report->shifts
                ->flatMap(fn($shift) => $shift->employees->map(fn($emp) => [
                    'id'         => $emp->id,
                    'name'       => $emp->full_name,
                    'position'   => $emp->position?->name,
                    'photo_url'  => $emp->photo ? asset($emp->photo) : null,
                    'function'   => $emp->pivot->function ?? null,
                    'status'     => $emp->pivot->status ?? null,
                    'shift'      => $shift->post,
                    'start_time' => $shift->start_time,
                    'end_time'   => $shift->end_time,
            ]))
                ->values();

            return $report;
        });

        return $this->paginated($reports);
    }
}
