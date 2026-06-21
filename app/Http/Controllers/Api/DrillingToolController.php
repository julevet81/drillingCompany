<?php

namespace App\Http\Controllers\Api;

use App\Models\DrillingTool;
use App\Models\DailyReportTool;
use App\Models\ToolType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrillingToolController extends BaseApiController
{
    /** GET /api/drilling-tools */
    public function index(Request $request): JsonResponse
    {
        $query = DrillingTool::with(['toolType', 'rig:id,name,code']);
        if ($allowedRigIds = $request->attributes->get('allowed_rig_ids')) {
            $query->whereIn('id', $allowedRigIds);
        }
        if ($request->filled('rig_id'))       $query->where('rig_id', $request->rig_id);
        if ($request->filled('tool_type_id')) $query->where('tool_type_id', $request->tool_type_id);
        return $this->success($query->get());
    }

    /** POST /api/drilling-tools */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool_type_id'      => ['required', 'exists:tool_types,id'],
            'name'              => ['nullable', 'string', 'max:255'],
            'external_diameter' => ['nullable', 'string', 'max:50'],
            'unit_length'       => ['nullable', 'numeric', 'min:0'],
            'total_quantity'    => ['required', 'integer', 'min:0'],
            'status'            => ['nullable', 'string'],
            'rig_id'            => ['required', 'exists:rigs,id'],
        ]);
        return $this->created(DrillingTool::create($data)->load('toolType'), 'Tool added');
    }

    /** PUT /api/drilling-tools/{drillingTool} */
    public function update(Request $request, DrillingTool $drillingTool): JsonResponse
    {
        $data = $request->validate([
            'tool_type_id'      => ['sometimes', 'exists:tool_types,id'],
            'name'              => ['nullable', 'string', 'max:255'],
            'external_diameter' => ['nullable', 'string', 'max:50'],
            'unit_length'       => ['nullable', 'numeric', 'min:0'],
            'total_quantity'    => ['sometimes', 'integer', 'min:0'],
            'status'            => ['nullable', 'string'],
        ]);
        $drillingTool->update($data);
        return $this->success($drillingTool->fresh('toolType'), 'Tool updated');
    }

    /** DELETE /api/drilling-tools/{drillingTool} */
    public function destroy(DrillingTool $drillingTool): JsonResponse
    {
        $drillingTool->delete($drillingTool->id);
        return $this->success(null, 'Tool deleted');
    }

    /** GET /api/tool-types */
    public function toolTypes(): JsonResponse
    {
        return $this->success(ToolType::all());
    }

    /** GET /api/drilling-tools/bha/{reportId} */
    public function bhaForReport(int $reportId): JsonResponse
    {
        $tools = DailyReportTool::with(['drillingTool.toolType'])
            ->where('report_id', $reportId)
            ->get()
            ->map(fn ($rt) => [
                'element'           => $rt->drillingTool?->toolType?->name,
                'external_diameter' => $rt->drillingTool?->external_diameter,
                'quantity'          => $rt->quantity_used,
                'length_m'          => $rt->total_length,
            ]);

        return $this->success([
            'tools'     => $tools,
            'total_bha' => round($tools->sum('length_m'), 2),
        ]);
    }
}
