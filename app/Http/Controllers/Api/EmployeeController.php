<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends BaseApiController
{
    /** GET /api/employees */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with([
            'position:id,name',
            'rig:id,name,code',
        ]);

        if ($allowedRigIds = $request->attributes->get('allowed_rig_ids')) {
            $query->whereIn('rig_id', $allowedRigIds);
        }

        if ($request->filled('position_id')) $query->where('position_id', $request->position_id);

        if ($request->filled('search')) {
            $query->where('full_name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('rig_id')) {
            $query->where('rig_id', $request->rig_id);
        }

        $employees = $query->latest()->paginate($request->per_page ?? 15);

        $employees->getCollection()->transform(function (Employee $employee) {
            $latestShift = DB::table('employee_shifts')
                ->join('shifts', 'shifts.id', '=', 'employee_shifts.shift_id')
                ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
                ->where('employee_shifts.employee_id', $employee->id)
                ->orderByDesc('daily_reports.report_date')
                ->select(
                    'employee_shifts.status',
                    'shifts.post',
                    'daily_reports.rig_id',
                    'daily_reports.report_date'
                )
                ->first();

            $employee->current_status   = $latestShift?->status ?? null;
            $employee->current_post     = $latestShift?->post ?? null;
            $employee->current_rig_id   = $latestShift?->rig_id ?? $employee->rig_id;
            $employee->last_report_date = $latestShift?->report_date ?? null;

            return $employee;
        });

        return $this->paginated($employees);
    }

    /** GET /api/employees/stats */
    public function stats(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();

        $counts = DB::table('employee_shifts')
            ->join('shifts', 'shifts.id', '=', 'employee_shifts.shift_id')
            ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
            ->whereDate('daily_reports.report_date', $date)
            ->selectRaw('employee_shifts.status, COUNT(*) as count')
            ->groupBy('employee_shifts.status')
            ->pluck('count', 'status');

        return $this->success([
            'total'    => Employee::count(),
            'onsite'   => $counts['onsite']  ?? 0,
            'on_base'  => $counts['onBase']  ?? 0,
            'on_leave' => $counts['onLeave'] ?? 0,
        ]);
    }

    /** POST /api/employees */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('uploads/employees'), $filename);

            $data['photo'] = 'uploads/employees/' . $filename;
        }

        return $this->created(Employee::create($data)->refresh()->load('position'), 'Employee added');
    }

    /** GET /api/employees/{employee} */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['position', 'rig:id,name,code']);

        $shifts = $employee->shifts()
            ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
            ->orderByDesc('daily_reports.report_date')
            ->select('shifts.*', 'daily_reports.report_date', 'daily_reports.rig_id')
            ->with('mudCharacteristic')
            ->limit(30)
            ->get();

        $employee->setRelation('shifts', $shifts);

        return $this->success($employee);
    }

    /** PUT /api/employees/{employee} */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {

            if ($employee->photo && file_exists(public_path($employee->photo))) {
                unlink(public_path($employee->photo));
            }

            $file = $request->file('photo');

            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('uploads/employees'), $filename);

            $data['photo'] = 'uploads/employees/' . $filename;
        }

        $employee->update($data);
        return $this->success($employee->refresh()->load('position'), 'Employee updated');
    }

    /** DELETE /api/employees/{employee} */
    public function destroy(Employee $employee): JsonResponse
    {
        if ($employee->photo && file_exists(public_path($employee->photo))) {
            unlink(public_path($employee->photo));
        }
        $employee->delete($employee->id);
        return $this->success(null, 'Employee deleted');
    }

    /** PATCH /api/employees/{employee}/status */
    public function updateStatus(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'status'   => ['required', 'in:onsite,onBase,onLeave'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
        ]);

        $shiftId = $request->shift_id;

        // ← لو لم يُرسَل shift_id، استخدم آخر shift سُجِّل للموظف
        if (!$shiftId) {
            $latestShift = DB::table('employee_shifts')
                ->join('shifts', 'shifts.id', '=', 'employee_shifts.shift_id')
                ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
                ->where('employee_shifts.employee_id', $employee->id)
                ->orderByDesc('daily_reports.report_date')
                ->select('shifts.id')
                ->first();

            $shiftId = $latestShift?->id;
        }

        if (!$shiftId) {
            return $this->error('No shift found for this employee.', 404);
        }

        $updated = DB::table('employee_shifts')
            ->where('employee_id', $employee->id)
            ->where('shift_id', $shiftId)
            ->update(['status' => $request->status]);

        if (!$updated) {
            return $this->error('Could not update status — record not found.', 404);
        }

        return $this->success(null, 'Status updated');
    }

    /** GET /api/positions */
    public function positions(): JsonResponse
    {
        return $this->success(\App\Models\Position::all());
    }

    public function add_position(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255']
        ]);

        return $this->created(Position::create($data), 'Position created');
    }
}
