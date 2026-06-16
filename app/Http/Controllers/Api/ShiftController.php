<?php

namespace App\Http\Controllers\Api;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends BaseApiController
{
    /** GET /api/shifts */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::with([
            'report:id,rig_id,report_date',
            'report.rig:id,name,code',
            'employees:id,full_name,position_id',
            'employees.position:id,name',
        ]);

        if ($request->filled('report_id')) $query->where('report_id', $request->report_id);
        if ($request->filled('periode'))   $query->where('periode', $request->periode);

        // فلترة بالـ rig أو التاريخ عبر التقرير
        if ($request->filled('rig_id')) {
            $query->whereHas('report', fn($q) => $q->where('rig_id', $request->rig_id));
        }
        if ($request->filled('date')) {
            $query->whereHas('report', fn($q) => $q->whereDate('report_date', $request->date));
        }

        return $this->paginated($query->latest()->paginate($request->per_page ?? 20));
    }

    /** GET /api/shifts/{shift} */
    public function show(Shift $shift): JsonResponse
    {
        $shift->load([
            'report:id,rig_id,report_date,status',
            'report.rig:id,name,code',
            'employees' => fn($q) => $q->with('position:id,name')->withPivot(['function', 'status']),
        ]);
        return $this->success($shift);
    }

    /** PUT /api/shifts/{shift} — تعديل الـ periode فقط، الموظفون عبر endpoints مخصصة */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'periode' => ['required', 'in:day,night'],
        ]);
        $shift->update($data);
        return $this->success($shift->fresh(), 'Shift updated');
    }

    /** DELETE /api/shifts/{shift} */
    public function destroy(Shift $shift): JsonResponse
    {
        if ($shift->report?->status === 'approved') {
            return $this->error('Cannot delete a shift from an approved report', 422);
        }
        $shift->delete();
        return $this->success(null, 'Shift deleted');
    }

    /** POST /api/shifts/{shift}/employees */
    public function attachEmployee(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'function'    => ['nullable', 'string', 'max:100'],
            'status'      => ['nullable', 'in:onsite,onBase,onLeave'],
        ]);

        $shift->employees()->syncWithoutDetaching([
            $data['employee_id'] => [
                'function' => $data['function'] ?? null,
                'status'   => $data['status'] ?? 'onsite',
            ],
        ]);

        return $this->success(null, 'Employee added to shift');
    }

    /** DELETE /api/shifts/{shift}/employees/{employee} */
    public function detachEmployee(Shift $shift, int $employeeId): JsonResponse
    {
        $shift->employees()->detach($employeeId);
        return $this->success(null, 'Employee removed from shift');
    }
}
