<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Report\StoreDailyReportRequest;
use App\Http\Requests\Report\UpdateDailyReportRequest;
use App\Models\DailyReport;
use App\Models\DailyReportTool;
use App\Models\DailyReportEquipment;
use App\Models\MaterialLog;
use App\Models\RigMaterial;
use App\Models\Shift;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyReportController extends BaseApiController
{
    /** GET /api/daily-reports */
    public function index(Request $request): JsonResponse
    {
        $query = DailyReport::with([
            'rig:id,name,code',
            'author:id,full_name',
            'reportEquipments.equipment:id,name,marque,serial_number,status,photo',
            'shifts.employees:id,full_name,photo,position_id',
            'shifts.employees.position:id,name',
        ])->withCount(['tools', 'reportEquipments', 'shifts']);

        if ($request->filled('date'))   $query->whereDate('report_date', $request->date);
        if ($request->filled('status')) $query->where('status', $request->status);

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('report_date', [$request->from, $request->to]);
        }

        $reports = $query->latest('report_date')->paginate($request->per_page ?? 15);

        $reports->getCollection()->transform(function (DailyReport $report) {
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
                    'id'        => $emp->id,
                    'name'      => $emp->full_name,
                    'position'  => $emp->position?->name,
                    'photo_url' => $emp->photo ? asset($emp->photo) : null,
                    'function'  => $emp->pivot->function ?? null,
                    'status'    => $emp->pivot->status ?? null,
                    'shift'     => $shift->periode,
                ]))
                ->unique('id')
                ->values();

            $report->getWorkersCountAttribute = $report->employees_list->count();
            $report->getTotalBhaLengthAttribute = (float) $report->tools->sum('total_length');

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
    try {
        $report = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['tools', 'equipments', 'shifts', 'materials']);
            $data['created_by']     = $request->user()->id;
            $data['daily_progress'] = $data['depth_end'] - $data['depth_start'];

            $report = DailyReport::create($data);

                // BHA Tools
                if ($request->filled('tools')) {
                    DailyReportTool::insert(
                        collect($request->tools)->map(fn($t) => [
                            'report_id'        => $report->id,
                            'drilling_tool_id' => $t['drilling_tool_id'],
                            'quantity_used'    => $t['quantity_used'] ?? 0,
                            'total_length'     => $t['total_length'] ?? 0,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ])->toArray()
                    );
                }

            // Equipments
            if ($request->filled('equipments')) {
                foreach ($request->equipments as $e) {
                    DailyReportEquipment::create([
                        'report_id'    => $report->id,
                        'equipment_id' => $e['equipment_id'],
                        'status'       => $e['status'] ?? 'Operational',
                    ]);
                }
            }

<<<<<<< HEAD
                // Shifts + موظفوهم — يُنشأ كل shift مع تقريره مباشرة
                if ($request->filled('shifts')) {
                    foreach ($request->shifts as $shiftData) {
                        $shift = Shift::create([
                            'report_id' => $report->id,
                            'periode'   => $shiftData['periode'],
                        ]);
=======
            // ─── Shifts / Employees ───────────────────────────────────────────
            // إذا أرسل المستخدم shifts يدوياً نستخدمها، وإلا نجلب تلقائياً
            // من جدول shifts بناءً على rig_id وتاريخ التقرير
            if ($request->filled('shifts')) {
                DailyReportEmployee::insert(collect($request->shifts)->map(fn ($s) => [
                    'report_id'  => $report->id,
                    'shift_id'   => $s['shift_id'],
                    'present'    => $s['present'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray());
            } else {
                // جلب كل الورديات المرتبطة بالـ rig وتاريخ التقرير تلقائياً
                $shifts = Shift::forRig($data['rig_id'])
                    ->forDate($data['report_date'])
                    ->pluck('id');

                if ($shifts->isNotEmpty()) {
                    DailyReportEmployee::insert($shifts->map(fn ($shiftId) => [
                        'report_id'  => $report->id,
                        'shift_id'   => $shiftId,
                        'present'    => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray());
                }
            }
>>>>>>> d9b73eb8f725500cefb7d29000dcc602528a9e5a

                        if (!empty($shiftData['employees'])) {
                            $shift->employees()->sync(
                                collect($shiftData['employees'])->mapWithKeys(fn($e) => [
                                    $e['employee_id'] => [
                                        'function' => $e['function'] ?? null,
                                        'status'   => $e['status'] ?? 'onsite',
                                    ],
                                ])->toArray()
                            );
                        }
                    }
<<<<<<< HEAD
=======

                    $rigMaterial->update(['quantity' => $newQty]);

                    MaterialLog::create([
                        'report_id'       => $report->id,
                        'rig_material_id' => $rigMaterial->id,
                        'log_date'        => $report->report_date,
                        'consumed'        => $m['consumed'] ?? 0,
                        'added'           => $m['added'] ?? 0,
                        'remaining'       => $newQty,
                    ]);
>>>>>>> d9b73eb8f725500cefb7d29000dcc602528a9e5a
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

                $report->rig->update(['current_depth' => $data['depth_end']]);

<<<<<<< HEAD
                return $report;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'daily_reports_rig_id_report_date_unique')) {
                return $this->error('A report for this rig and date already exists.', 422);
            }
            throw $e;
        }

        return $this->created(
            $report->load([
                'tools.drillingTool.toolType',
                'reportEquipments.equipment',
                'shifts.employees',
                'rig:id,name,code',
            ]),
            'Daily report created'
        );
=======
            return $report;
        });
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'daily_reports.rig_id, daily_reports.report_date')) {
            return $this->error('A report for this rig and date already exists.', 422);
        }
        throw $e;
>>>>>>> d9b73eb8f725500cefb7d29000dcc602528a9e5a
    }

    return $this->created(
        $report->load([
            'tools.drillingTool.toolType',
            'reportEquipments.equipment',
            'reportEmployees.shift.employees.position',
            'rig:id,name,code',
        ]),
        'Daily report created'
    );
}

    /** GET /api/daily-reports/{report} */
    public function show(DailyReport $daily_report): JsonResponse
    {
        $daily_report->load([
            'rig:id,name,code,location_id',
            'rig.location:id,name',
            'author:id,full_name',
            'tools.drillingTool.toolType:id,name',
            'reportEquipments.equipment:id,name,serial_number,status',
            'shifts.employees:id,full_name,position_id',
            'shifts.employees.position:id,name',
            'materialLogs.rigMaterial.materialType:id,name,unit',
        ]);

        $employees = $daily_report->shifts
            ->flatMap(fn($shift) => $shift->employees->map(fn($emp) => [
                'id'       => $emp->id,
                'name'     => $emp->full_name,
                'position' => $emp->position?->name,
                'function' => $emp->pivot->function ?? null,
                'status'   => $emp->pivot->status ?? null,
                'shift'    => $shift->periode,
            ]))
            ->unique('id')
            ->values();

        return $this->success(array_merge($daily_report->toArray(), [
            'total_bha_length' => $daily_report->total_bha_length,
            'workers_count'    => $employees->count(),
            'employees'        => $employees,
        ]));
    }

    /** PUT /api/daily-reports/{report} */
    public function update(UpdateDailyReportRequest $request, DailyReport $report): JsonResponse
    {
        if ($report->status !== 'draft') {
            return $this->error('Only draft reports can be edited', 422);
        }

        DB::transaction(function () use ($request, $report) {
            $data = $request->safe()->except(['tools', 'equipments', 'shifts', 'materials']);

            if (isset($data['depth_start'], $data['depth_end'])) {
                $data['daily_progress'] = $data['depth_end'] - $data['depth_start'];
            }

            $report->update($data);

            if ($request->filled('tools')) {
                $report->tools()->delete();
                DailyReportTool::insert(
                    collect($request->tools)->map(fn($t) => [
                        'report_id'        => $report->id,
                        'drilling_tool_id' => $t['drilling_tool_id'],
                        'quantity_used'    => $t['quantity_used'] ?? 0,
                        'total_length'     => $t['total_length'] ?? 0,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ])->toArray()
                );
            }

            // تحديث موظفي الـ shifts — sync بدون حذف الـ shift نفسه
            if ($request->filled('shifts')) {
                foreach ($request->shifts as $shiftData) {
                    $shift = $report->shifts()->firstOrCreate([
                        'periode' => $shiftData['periode'],
                    ]);

                    if (!empty($shiftData['employees'])) {
                        $shift->employees()->sync(
                            collect($shiftData['employees'])->mapWithKeys(fn($e) => [
                                $e['employee_id'] => [
                                    'function' => $e['function'] ?? null,
                                    'status'   => $e['status'] ?? 'onsite',
                                ],
                            ])->toArray()
                        );
                    }
                }
            }
        });

        return $this->success(
            $report->fresh(['tools', 'reportEquipments', 'shifts.employees']),
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
}