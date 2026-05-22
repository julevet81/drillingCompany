<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Rig\StoreRigRequest;
use App\Http\Requests\Rig\UpdateRigRequest;
use App\Models\DailyReport;
use App\Models\Rig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RigController extends BaseApiController
{
    /** GET /api/rigs */
    public function index(Request $request): JsonResponse
    {
        $query = Rig::with(['location:id,name,state', 'manager:id,full_name'])
            ->withCount(['equipments', 'dailyReports']);

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('location_id')) $query->where('location_id', $request->location_id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }


        $rigs = $query->latest()->paginate($request->per_page ?? 15);

        return $this->paginated($rigs);
    }

    /** GET /api/rigs/stats */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('rigs:stats', 120, fn () => [
            'total'      => Rig::count(),
            'drilling'   => Rig::where('status', 'drilling')->count(),
            'stopped'    => Rig::where('status', 'stopped')->count(),
            'fishing'    => Rig::where('status', 'fishing')->count(),
            'casing'     => Rig::where('status', 'casing')->count(),
            'dtm'        => Rig::where('status', 'dtm')->count(),
            'developing' => Rig::where('status', 'developing')->count(),
            'by_status'  => Rig::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')->pluck('count', 'status'),
        ]);

        return $this->success($stats);
    }

    /** POST /api/rigs */
    public function store(StoreRigRequest $request): JsonResponse
    {
        $rig = DB::transaction(fn () => Rig::create($request->validated()));
        Cache::forget('dashboard:stats');
        Cache::forget('rigs:stats');

        return $this->created($rig->load('location:id,name,state', 'manager:id,full_name'), 'Rig created successfully');
    }


    public function show(Rig $rig): JsonResponse
    {
        $rig->load([
            'location:id,name,state',
            'manager:id,full_name,email',
            'equipments:id,current_rig_id,name,marque,serial_number,hours_of_operation,status',
            'drillingTools.toolType:id,name',
            'rigMaterials.materialType:id,name,unit',
            'shifts' => fn ($q) => $q
                ->whereDate('date', today())
                ->with(['employees:id,full_name,position_id', 'employees.position:id,name']),
        ]);

        // Recent daily reports (last 10 for the timeline table)
        $recentReports = DailyReport::forRig($rig->id)
            ->latest('report_date')
            ->limit(10)
            ->get(['id', 'report_date', 'depth_end', 'daily_progress', 'incidents', 'npt_hours']);

        // Depth progress timeline (for the chart: last 14 days)
        $depthTimeline = DailyReport::forRig($rig->id)
            ->where('report_date', '>=', now()->subDays(14))
            ->orderBy('report_date')
            ->get(['report_date', 'depth_end'])
            ->map(fn ($r) => [
                'date'  => $r->report_date->format('m-d'),
                'depth' => (float) $r->depth_end,
            ]);

        // Today's crew from active shifts
        $crew = collect();
        foreach ($rig->shifts as $shift) {
            foreach ($shift->employees as $emp) {
                $crew->push([
                    'id'       => $emp->id,
                    'name'     => $emp->full_name,
                    'position' => $emp->position?->name,
                    'shift'    => ucfirst($shift->periode),
                ]);
            }
        }

        return $this->success([
            'rig' => array_merge($rig->only([
                'id', 'name', 'code', 'status', 'drilling_phase',
                'current_depth', 'target_depth',
                'start_date', 'end_date', 'notes'
                ]), [
                'location'           => $rig->location?->name,
                'manager'            => $rig->manager?->full_name,
                'progress_percentage' => $rig->progress_percentage,
                'days_remaining'     => $rig->days_remaining,
             ]),
            'equipments'     => $rig->equipments,
            'drilling_tools' => $rig->drillingTools,
            'materials'      => $rig->rigMaterials->map(fn ($m) => [
                'id'       => $m->id,
                'name'     => $m->materialType?->name,
                'unit'     => $m->materialType?->unit,
                'quantity' => (float) $m->quantity,
                'capacity' => (float) $m->capacity,
                'filled_percentage' => $m->filled_percentage,
                'is_low'   => $m->isLow(),
            ]),
            'crew'           => $crew->unique('id')->values(),
            'recent_reports' => $recentReports,
            'depth_timeline' => $depthTimeline,
        ]);
    }

    /** PUT /api/rigs/{rig} */
    public function update(UpdateRigRequest $request, Rig $rig): JsonResponse
    {
        DB::transaction(fn () => $rig->update($request->validated()));
        Cache::forget('dashboard:stats');
        Cache::forget('rigs:stats');

        return $this->success($rig->fresh('location:id,name,state', 'manager:id,full_name'), 'Rig updated');
    }

    /** DELETE /api/rigs/{rig} */
    public function destroy(Rig $rig): JsonResponse
    {
        $rig->delete();
        Cache::forget('dashboard:stats');
        Cache::forget('rigs:stats');

        return $this->success(null, 'Rig deleted');
    }

    /** PATCH /api/rigs/{rig}/status */
    public function updateStatus(Request $request, Rig $rig): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:' . implode(',', Rig::STATUSES)],
        ]);

        $rig->update(['status' => $request->status]);
        Cache::forget('dashboard:stats');
        Cache::forget('rigs:stats');

        return $this->success($rig->only(['id', 'status']), 'Status updated');
    }
}
