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
        $query = Rig::with([
            'location:id,name,state',
            'manager:id,full_name',
            'rigMaterials' => fn($q) => $q->whereHas(
                'materialType',
                fn($q2) => $q2->where('name', 'Diesel Fuel')
            )->with('materialType:id,name,unit'),
        ])
            ->withCount(['equipments', 'dailyReports']);

        if ($allowedRigIds = $request->attributes->get('allowed_rig_ids')) {
            $query->whereIn('id', $allowedRigIds);
        }

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('location_id')) $query->where('location_id', $request->location_id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        $rigs = $query->latest()->paginate($request->per_page ?? 15);

        // Transform the collection to include fuel info
        $rigs->getCollection()->transform(function (Rig $rig) {
            $fuel = $rig->rigMaterials->first();

            $rig->fuel = $fuel ? [
                'quantity'          => (float) $fuel->quantity,
                'capacity'          => (float) $fuel->capacity,
                'filled_percentage' => $fuel->filled_percentage,
                'is_low'            => $fuel->isLow(),
            ] : null;

            unset($rig->rigMaterials);

            return $rig;
        });

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


    public function show(Rig $rig, Request $request): JsonResponse
    {
        $allowedRigIds = $request->attributes->get('allowed_rig_ids');
        if ($allowedRigIds !== null && !$allowedRigIds->contains($rig->id)) {
            return $this->forbidden('You are not authorized to view this rig');
        }

        $rig->load([
            'location:id,name,state',
            'manager:id,full_name,email',
            'equipments:id,current_rig_id,name,marque,serial_number,hours_of_operation,status',
            'drillingTools.toolType:id,name',
            'rigMaterials.materialType:id,name,unit',
        ]);

        // ← جلب الـ shifts عبر تقرير اليوم
        $todayReport = DailyReport::where('rig_id', $rig->id)
            ->whereDate('report_date', today())
            ->with(['shifts.employees:id,full_name,position_id', 'shifts.employees.position:id,name'])
            ->first();

        $recentReports = DailyReport::forRig($rig->id)
            ->latest('report_date')
            ->limit(10)
            ->get(['id', 'report_date', 'depth_end', 'daily_progress', 'incidents', 'npt_hours']);

        $depthTimeline = DailyReport::forRig($rig->id)
            ->where('report_date', '>=', now()->subDays(14))
            ->orderBy('report_date')
            ->get(['report_date', 'depth_end'])
            ->map(fn($r) => [
                'date'  => $r->report_date->format('m-d'),
                'depth' => (float) $r->depth_end,
            ]);

        // ← Crew من شifts تقرير اليوم
        $crew = collect();
        if ($todayReport) {
            foreach ($todayReport->shifts as $shift) {
                foreach ($shift->employees as $emp) {
                    $crew->push([
                        'id'       => $emp->id,
                        'name'     => $emp->full_name,
                        'position' => $emp->position?->name,
                        'shift'    => ucfirst($shift->post), // ← post بدل periode
                    ]);
                }
            }
        }

        return $this->success([
            'rig' => array_merge($rig->only([
                'id',
                'name',
                'code',
                'status',
                'drilling_phase',
                'current_depth',
                'target_depth',
                'start_date',
                'end_date',
                'notes'
            ]), [
                'location'            => $rig->location?->name,
                'manager'             => $rig->manager?->full_name,
                'progress_percentage' => $rig->progress_percentage,
                'days_remaining'      => $rig->days_remaining,
            ]),
            'equipments'     => $rig->equipments,
            'drilling_tools' => $rig->drillingTools,
            'materials'      => $rig->rigMaterials->map(fn($m) => [
                'id'                => $m->id,
                'name'              => $m->materialType?->name,
                'unit'              => $m->materialType?->unit,
                'quantity'          => (float) $m->quantity,
                'capacity'          => (float) $m->capacity,
                'filled_percentage' => $m->filled_percentage,
                'is_low'            => $m->isLow(),
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
            'notes' => ['nullable', 'string'],
        ]);

        $rig->update(['status' => $request->status, 'notes' => $request->notes]);
        Cache::forget('dashboard:stats');
        Cache::forget('rigs:stats');

        return $this->success($rig->only(['id', 'status', 'notes']), 'Status updated');
    }
}
