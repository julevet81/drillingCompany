<?php

namespace App\Http\Controllers\Api;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends BaseApiController
{
    /** GET /api/shifts */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::with(['rig:id,name,code', 'employees:id,full_name,position_id']);
        if ($request->filled('rig_id'))  $query->where('rig_id', $request->rig_id);
        if ($request->filled('date'))    $query->whereDate('date', $request->date);
        if ($request->filled('periode')) $query->where('periode', $request->periode);

        return $this->paginated($query->latest('date')->paginate($request->per_page ?? 20));
    }

    /** POST /api/shifts */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'                    => ['required', 'date'],
            'periode'                 => ['required', 'in:day,night'],
            'rig_id'                  => ['nullable', 'exists:rigs,id'],
            'employees'               => ['nullable', 'array'],
            'employees.*.employee_id' => ['required', 'exists:employees,id'],
            'employees.*.function'    => ['nullable', 'string', 'max:100'],
            'employees.*.status'      => ['nullable', 'in:onsite,onBase,onLeave'],
        ]);

        $shift = DB::transaction(function () use ($data) {
            $shift = Shift::create(['date' => $data['date'], 'periode' => $data['periode'], 'rig_id' => $data['rig_id'] ?? null]);

            if (!empty($data['employees'])) {
                $shift->employees()->sync(collect($data['employees'])->mapWithKeys(fn ($e) => [
                    $e['employee_id'] => ['function' => $e['function'] ?? null, 'status' => $e['status'] ?? 'onsite'],
                ])->toArray());
            }

            return $shift;
        });

        return $this->created($shift->load('employees:id,full_name'), 'Shift created');
    }

    /** GET /api/shifts/{shift} */
    public function show(Shift $shift): JsonResponse
    {
        $shift->load(['rig:id,name,code', 'employees' => fn ($q) => $q->with('position:id,name')->withPivot(['function', 'status'])]);
        return $this->success($shift);
    }

    /** PUT /api/shifts/{shift} */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'date'    => ['sometimes', 'date'],
            'periode' => ['sometimes', 'in:day,night'],
            'rig_id'  => ['nullable', 'exists:rigs,id'],
        ]);
        $shift->update($data);
        return $this->success($shift->fresh(), 'Shift updated');
    }

    /** DELETE /api/shifts/{shift} */
    public function destroy(Shift $shift): JsonResponse
    {
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
            $data['employee_id'] => ['function' => $data['function'] ?? null, 'status' => $data['status'] ?? 'onsite'],
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
