<?php

namespace App\Http\Controllers\Api;

use App\Models\MaterialLog;
use App\Models\MaterialType;
use App\Models\Rig;
use App\Models\RigMaterial;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends BaseApiController
{
    /**
     * GET /api/materials/types
     * List all material types (Diesel Fuel, Bentonite, Barite, Cement…)
     */
    public function types(): JsonResponse
    {
        return $this->success(MaterialType::all());
    }

    /**
     * POST /api/materials/types
     */
    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:material_types,name'],
            'unit' => ['required', 'string', 'max:20'],
        ]);

        return $this->created(MaterialType::create($data), 'Material type created');
    }

    /**
     * GET /api/materials/fuel-stats
     * Aggregated fuel stats for the Fuel Tracking tab (uses Diesel Fuel material type)
     */
    public function fuelStats(): JsonResponse
    {
        $yesterday = today()->subDay();
    
        $fuelMaterials = RigMaterial::with('rig:id,name,code')
            ->whereHas('materialType', fn ($q) => $q->where('name', 'Diesel Fuel'))
            ->get();
    
        $stats = $fuelMaterials->map(function ($material) use ($yesterday) {
            $dailyConsumption = MaterialLog::where('rig_material_id', $material->id)
                ->whereDate('log_date', $yesterday)
                ->sum('consumed');
    
            $avgDaysRemaining = $dailyConsumption > 0
                ? round($material->quantity / $dailyConsumption)
                : null;
    
            return [
                'rig_id'              => $material->rig_id,
                'rig_name'            => $material->rig->name ?? null,
                'rig_code'            => $material->rig->code ?? null,
                'total_capacity_l'    => (float) $material->capacity,
                'current_stock_l'     => (float) $material->quantity,
                'daily_consumption_l' => (float) $dailyConsumption,
                'avg_days_remaining'  => $avgDaysRemaining,
                'filled_percentage'   => $material->filled_percentage,
            ];
        });
    
        return $this->success($stats);
    }

    /**
     * GET /api/materials/fuel-levels
     * Per-rig fuel levels for the progress bars
     */
    public function fuelLevels(): JsonResponse
    {
        $levels = RigMaterial::with(['rig:id,name,code', 'materialType:id,name,unit'])
            ->whereHas('materialType', fn ($q) => $q->where('name', 'Diesel Fuel'))
            ->get()
            ->map(fn (RigMaterial $m) => [
                'rig_code'          => $m->rig?->code,
                'rig_name'          => $m->rig?->name,
                'quantity'          => (float) $m->quantity,
                'capacity'          => (float) $m->capacity,
                'filled_percentage' => $m->filled_percentage,
                'is_low'            => $m->isLow(),
                'days_remaining'    => $this->estimateDays($m),
            ]);

        return $this->success($levels);
    }

    /**
     * GET /api/materials/fuel-weekly
     */
    public function fuelWeekly(): JsonResponse
    {
        $data = MaterialLog::whereHas(
            'rigMaterial',
            fn ($q) => $q->whereHas('materialType', fn ($q2) => $q2->where('name', 'Diesel Fuel'))
        )
            ->selectRaw('log_date, SUM(consumed) as total')
            ->where('log_date', '>=', now()->startOfWeek())
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get()
            ->map(fn ($r) => [
                'day'   => Carbon::parse($r->log_date)->format('D'),
                'total' => round($r->total, 2),
            ]);

        return $this->success($data);
    }

    /**
     * GET /api/materials/rig/{rig}
     * All materials for a specific rig (shown in Rig Detail sidebar)
     */
    public function forRig(Rig $rig): JsonResponse
    {
        $materials = RigMaterial::with('materialType:id,name,unit')
            ->where('rig_id', $rig->id)
            ->get()
            ->map(fn (RigMaterial $m) => [
                'id'                => $m->id,
                'name'              => $m->materialType?->name,
                'unit'              => $m->materialType?->unit,
                'quantity'          => (float) $m->quantity,
                'capacity'          => (float) $m->capacity,
                'filled_percentage' => $m->filled_percentage,
                'is_low'            => $m->isLow(),
            ]);

        return $this->success($materials);
    }

    /**
     * POST /api/materials/rig/{rig}
     * Set / update a material stock entry for a rig
     */
    public function setForRig(Request $request, Rig $rig): JsonResponse
    {
        $data = $request->validate([
            'material_type_id' => ['required', 'exists:material_types,id'],
            'quantity'         => ['required', 'numeric', 'min:0'],
            'capacity'         => ['nullable', 'numeric', 'min:0'],
        ]);

        $rigMaterial = RigMaterial::updateOrCreate(
            ['rig_id' => $rig->id, 'material_type_id' => $data['material_type_id']],
            ['quantity' => $data['quantity'], 'capacity' => $data['capacity'] ?? null]
        );

        return $this->success($rigMaterial->load('materialType'), 'Material updated');
    }

    /**
     * GET /api/materials/{rigMaterial}/logs
     */
    public function logs(Request $request, RigMaterial $rigMaterial): JsonResponse
    {
        $logs = MaterialLog::where('rig_material_id', $rigMaterial->id)
            ->when($request->filled('from'), fn ($q) => $q->where('log_date', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->where('log_date', '<=', $request->to))
            ->latest('log_date')
            ->paginate(30);

        return $this->paginated($logs);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function estimateDays(RigMaterial $m): ?int
    {
        $avgDaily = MaterialLog::where('rig_material_id', $m->id)
            ->where('log_date', '>=', now()->subDays(7))
            ->avg('consumed');

        if (!$avgDaily || $avgDaily == 0) return null;
        return (int) floor($m->quantity / $avgDaily);
    }
}