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
        $query = Employee::with(['position:id,name']);

        if ($request->filled('position_id')) $query->where('position_id', $request->position_id);
        //if ($request->filled('search'))      $query->where('full_name', 'like', '%' . $request->search . '%');

        // if ($request->filled('status')) {
        //     $query->whereHas('shifts', fn ($q) => $q
        //         ->whereDate('date', today())
        //         ->where('employee_shifts.status', $request->status));
        // }

        if ($request->filled('rig_id')) {
            $query->whereHas('shifts', fn ($q) => $q
                ->where('rig_id', $request->rig_id)
                ->whereDate('date', today()));
        }

        return $this->paginated($query->latest()->paginate($request->per_page ?? 15));
    }

    /** GET /api/employees/stats */
    public function stats(Request $request): JsonResponse
    {
        $date   = $request->date ?? today()->toDateString();
        $counts = EmployeeShift::whereHas('shift', fn ($q) => $q->whereDate('date', $date))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return $this->success([
            'total'    => Employee::count('full_name'),
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
        $employee->load(['position', 'shifts' => fn ($q) => $q->latest('date')->limit(30)]);
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
            'shift_id' => ['required', 'exists:shifts,id'],
        ]);

        DB::table('employee_shifts')
            ->where('employee_id', $employee->id)
            ->where('shift_id', $request->shift_id)
            ->update(['status' => $request->status]);

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
