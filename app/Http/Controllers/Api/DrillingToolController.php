<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyReportTool;
use App\Models\DrillingTool;
use App\Models\Rig;
use App\Models\ToolType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrillingToolController extends BaseApiController
{
    /** GET /api/drilling-tools */
    public function index(Request $request): JsonResponse
    {
        $query = DrillingTool::with('toolType:id,name', 'rig:id,name,code');

        $allowedRigIds = $request->attributes->get('allowed_rig_ids');
        if ($allowedRigIds) {
            $query->whereIn('rig_id', $allowedRigIds);
        }

        if ($request->filled('rig_id'))       $query->where('rig_id', $request->rig_id);
        if ($request->filled('tool_type_id')) $query->where('tool_type_id', $request->tool_type_id);

        return $this->paginated($query->latest()->paginate($request->per_page ?? 20));
    }

    /** GET /api/drilling-tools/by-rig/{rig} */
    public function byRig(Rig $rig, Request $request): JsonResponse
    {
        $allowedRigIds = $request->attributes->get('allowed_rig_ids');
        if ($allowedRigIds !== null && !$allowedRigIds->contains($rig->id)) {
            return $this->forbidden('You are not authorized to view tools for this rig');
        }

        $tools = DrillingTool::where('rig_id', $rig->id)
            ->with('toolType:id,name')
            ->get();

        return $this->success($tools);
    }

    /** POST /api/drilling-tools */
    public function store(Request $request): JsonResponse
    {
        $allowedRigIds = $request->attributes->get('allowed_rig_ids');

        $data = $request->validate([
            'rig_id'             => $allowedRigIds
                ? ['nullable'] // يُتجاهل ويُستبدل تلقائياً لو rig_manager
                : ['required', 'exists:rigs,id'],
            'tool_type_id'       => ['required', 'exists:tool_types,id'],
            'name'                => ['nullable', 'string', 'max:255'],
            'external_diameter'   => ['nullable', 'string', 'max:50'],
            'unit_length'         => ['nullable', 'numeric', 'min:0'],
            'total_quantity'      => ['nullable', 'integer', 'min:0'],
            'status'              => ['nullable', 'string', 'max:50'],
        ]);

        if ($allowedRigIds) {
            // rig_manager: يُفرض عليه الـ rig الخاص به دائماً
            if ($allowedRigIds->count() > 1) {
                // لو يدير أكثر من rig، يجب أن يحدد rig_id ضمن المسموح له
                $request->validate([
                    'rig_id' => ['required', 'exists:rigs,id'],
                ]);
                if (!$allowedRigIds->contains($request->rig_id)) {
                    return $this->forbidden('You can only add tools to your assigned rig(s)');
                }
                $data['rig_id'] = $request->rig_id;
            } else {
                $data['rig_id'] = $allowedRigIds->first();
            }
        }

        $tool = DrillingTool::create($data);

        return $this->created($tool->load('toolType', 'rig:id,name,code'), 'Tool created');
    }

    /** PUT /api/drilling-tools/{drillingTool} */
    public function update(Request $request, DrillingTool $drillingTool): JsonResponse
    {
        $allowedRigIds = $request->attributes->get('allowed_rig_ids');
        if ($allowedRigIds !== null && !$allowedRigIds->contains($drillingTool->rig_id)) {
            return $this->forbidden('You are not authorized to update this tool');
        }

        $data = $request->validate([
            'name'              => ['nullable', 'string', 'max:255'],
            'external_diameter' => ['nullable', 'string', 'max:50'],
            'unit_length'       => ['nullable', 'numeric', 'min:0'],
            'total_quantity'    => ['nullable', 'integer', 'min:0'],
            'status'            => ['nullable', 'string', 'max:50'],
            // rig_id غير قابل للتعديل بعد الإنشاء — نفس منطق daily_reports
        ]);

        $drillingTool->update($data);

        return $this->success($drillingTool->fresh(['toolType', 'rig:id,name,code']), 'Tool updated');
    }

    public function destroy(DrillingTool $drillingTool, Request $request): JsonResponse
    {
        $allowedRigIds = $request->attributes->get('allowed_rig_ids');
        if ($allowedRigIds !== null && !$allowedRigIds->contains($drillingTool->rig_id)) {
            return $this->forbidden('You are not authorized to delete this tool');
        }

        $drillingTool->delete();

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
