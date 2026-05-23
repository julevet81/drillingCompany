<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyReport;
use App\Models\EmployeeShift;
use App\Models\Equipment;
use App\Models\Rig;
use App\Models\RigMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseApiController
{
    /** GET /api/dashboard/stats */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('dashboard:stats', 60, function () {
            $now       = now();
            $lastMonth = now()->subMonth();

            $totalRigs  = Rig::count();
            $activeRigs = Rig::where('status', 'active')->count();

            $completedThisMonth = Rig::where('status', 'completed')
                ->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count();

            $completedLastMonth = Rig::where('status', 'completed')
                ->whereMonth('updated_at', $lastMonth->month)->whereYear('updated_at', $lastMonth->year)->count();

            $totalDepth     = Rig::sum('current_depth');
            $lastMonthDepth = DailyReport::where('report_date', '<', $lastMonth)->sum('daily_progress');

            $avgProgress = DailyReport::whereDate('report_date', today())->avg('daily_progress') ?? 0;

            $machinesInUse  = Equipment::whereNotNull('current_rig_id')->count();
            $totalEquipment = Equipment::count();
            $efficiency     = $totalEquipment > 0 ? round(($machinesInUse / $totalEquipment) * 100) : 0;

            return [
                'total_rigs'             => $totalRigs,
                'active_rigs'            => $activeRigs,
                'completed_month'        => $completedThisMonth,
                'completed_delta'        => $completedThisMonth - $completedLastMonth,
                'stand_by'               => Rig::where('status', 'paused')->count(),
                'total_depth_m'          => (float) $totalDepth,
                'avg_daily_progress'     => round($avgProgress, 2),
                'machines_in_use'        => $machinesInUse,
                'operational_efficiency' => $efficiency,
            ];
        });

        return $this->success($stats);
    }

    /** GET /api/dashboard/depth-chart */
    public function depthChart(): JsonResponse
    {
        $data = Cache::remember('dashboard:depth-chart', 300, function () {
            return DailyReport::selectRaw(
                'YEAR(report_date) as year, MONTH(report_date) as month, SUM(daily_progress) as total'
            )
                ->whereYear('report_date', now()->year)
                ->groupBy('year', 'month')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => [
                    'month' => \Carbon\Carbon::createFromDate($r->year, $r->month, 1)->format('M'),
                    'depth' => round($r->total, 2),
                ]);
        });

        return $this->success($data);
    }

    /** GET /api/dashboard/weekly-progress */
    public function weeklyProgress(): JsonResponse
    {
        $data = Cache::remember('dashboard:weekly', 120, function () {
            return DailyReport::selectRaw('report_date, SUM(daily_progress) as total')
                ->where('report_date', '>=', now()->startOfWeek())
                ->groupBy('report_date')
                ->orderBy('report_date')
                ->get()
                ->map(fn ($r) => [
                    'day'   => \Carbon\Carbon::parse($r->report_date)->format('D'),
                    'depth' => round($r->total, 2),
                ]);
        });

        return $this->success($data);
    }

    /** GET /api/dashboard/rig-status */
    public function rigStatusDistribution(): JsonResponse
    {
        return $this->success(
            Rig::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status')
        );
    }

    /** GET /api/dashboard/active-rigs */
    public function activeRigsOverview(): JsonResponse
    {
        $rigs = Rig::with('location:id,name')
            ->where('status', 'drilling')
            ->select(['id', 'name', 'code', 'location_id', 'status', 'current_depth', 'target_depth', 'drilling_phase'])
            ->get()
            ->map(fn ($rig) => [
                'id'             => $rig->id,
                'name'           => $rig->name,
                'code'           => $rig->code,
                'location'       => $rig->location?->name,
                'status'         => $rig->status,
                'drilling_phase' => $rig->drilling_phase,
                'current_depth'  => $rig->current_depth,
                'target_depth'   => $rig->target_depth,
                'progress'       => $rig->progress_percentage,
            ]);

        return $this->success($rigs);
    }

    /** GET /api/dashboard/alerts */
    public function alerts(): JsonResponse
    {
        $alerts = [];

        // Low fuel (Diesel) alerts
        $lowFuel = RigMaterial::with(['rig:id,name,code', 'materialType:id,name,unit'])
            ->whereHas('materialType', fn ($q) => $q->where('name', 'Diesel Fuel'))
            ->whereRaw('(quantity / NULLIF(capacity,0)) * 100 < 20')
            ->get();

        foreach ($lowFuel as $m) {
            $alerts[] = [
                'type'    => 'warning',
                'title'   => $m->rig?->code ?? 'Tank',
                'message' => 'Fuel level below 20%',
                'time'    => now()->diffForHumans(),
            ];
        }

        // Maintenance rigs
        foreach (Rig::where('status', 'maintenance')->get() as $rig) {
            $alerts[] = [
                'type'    => 'info',
                'title'   => $rig->code,
                'message' => 'Scheduled maintenance due',
                'time'    => $rig->updated_at->diffForHumans(),
            ];
        }

        return $this->success($alerts);
    }

    /** GET /api/dashboard/system-status */
    public function systemStatus(): JsonResponse
    {
        $status = Cache::remember('dashboard:system-status', 60, fn () => [
            'active_rigs'      => Rig::where('status', 'drilling')->count(),
            'running_machines' => Equipment::whereNotNull('current_rig_id')->count(),
            'field_workers'    => EmployeeShift::where('status', 'onsite')
                ->whereHas('shift', fn ($q) => $q->whereDate('date', today()))
                ->count(),
        ]);

        return $this->success($status);
    }
}
