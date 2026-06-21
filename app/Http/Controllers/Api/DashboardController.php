<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyReport;
use App\Models\Equipment;
use App\Models\Rig;
use App\Models\RigMaterial;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    /** GET /api/dashboard/stats */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('dashboard:stats', 60, function () {
            $now       = now();
            $lastMonth = now()->subMonth();

            $totalRigs  = Rig::count();
            $activeRigs = Rig::where('status', 'drilling')->count();

            $completedThisMonth = Rig::where('status', 'completed')
                ->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count();

            $completedLastMonth = Rig::where('status', 'completed')
                ->whereMonth('updated_at', $lastMonth->month)->whereYear('updated_at', $lastMonth->year)->count();

            $totalDepth  = Rig::sum('current_depth');
            $avgProgress = DailyReport::whereDate('report_date', today())->avg('daily_progress') ?? 0;

            $machinesInUse  = Equipment::whereNotNull('current_rig_id')->count();
            $totalEquipment = Equipment::count();
            $efficiency     = $totalEquipment > 0 ? round(($machinesInUse / $totalEquipment) * 100) : 0;

            return [
                'total_rigs'             => $totalRigs,
                'active_rigs'            => $activeRigs,
                'completed_month'        => $completedThisMonth,
                'completed_delta'        => $completedThisMonth - $completedLastMonth,
                'stand_by'               => Rig::where('status', 'stopped')->count(),
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
                ->map(fn($r) => [
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
                ->map(fn($r) => [
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
        $rigs = Rig::with('location:id,name,latitude,longitude')
            ->whereIn('status', ['developing', 'drilling', 'fishing', 'dtm'])
            ->select(['id', 'name', 'code', 'location_id', 'status', 'current_depth', 'target_depth', 'drilling_phase'])
            ->get()
            ->map(fn($rig) => [
                'id'             => $rig->id,
                'name'           => $rig->name,
                'code'           => $rig->code,
                'location'       => $rig->location?->name,
                'latitude'       => $rig->location?->latitude,
                'longitude'      => $rig->location?->longitude,
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

        $lowFuel = RigMaterial::with(['rig:id,name,code', 'materialType:id,name,unit'])
            ->whereHas('materialType', fn($q) => $q->where('name', 'Diesel Fuel'))
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

        foreach (Rig::where('status', 'stopped')->get() as $rig) {
            $alerts[] = [
                'type'    => 'info',
                'title'   => $rig->code,
                'message' => 'Rig is stopped',
                'time'    => $rig->updated_at->diffForHumans(),
            ];
        }

        return $this->success($alerts);
    }

    /** GET /api/dashboard/system-status */
    public function systemStatus(): JsonResponse
    {
        $status = Cache::remember('dashboard:system-status', 60, function () {
            // عدد الموظفين onsite عبر shift_employees → shifts → reports (اليوم)
            $fieldWorkers = DB::table('employee_shifts')
                ->join('shifts', 'shifts.id', '=', 'employee_shifts.shift_id')
                ->join('daily_reports', 'daily_reports.id', '=', 'shifts.report_id')
                ->where('employee_shifts.status', 'onsite')
                ->whereDate('daily_reports.report_date', today())
                ->count();

            return [
                'active_rigs'      => Rig::where('status', 'drilling')->count(),
                'running_machines' => Equipment::whereNotNull('current_rig_id')->count(),
                'field_workers'    => $fieldWorkers,
            ];
        });

        return $this->success($status);
    }
}
