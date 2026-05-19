<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends BaseApiController
{
    /** GET /api/equipments */
    public function index(Request $request): JsonResponse
    {
        $query = Equipment::with('rig:id,name,code');

        if ($request->filled('rig_id'))  $query->where('current_rig_id', $request->rig_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%$s%")
                ->orWhere('serial_number', 'like', "%$s%")
                ->orWhere('marque', 'like', "%$s%"));
        }

        $equipments = $query->latest()->paginate($request->per_page ?? 15);
        return $this->paginated($equipments);
    }

    /** POST /api/equipments */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $equipment = Equipment::create($request->validated());
        return $this->created($equipment->load('rig:id,name,code'), 'Equipment added');
    }

    /** GET /api/equipments/{equipment} */
    public function show(Equipment $equipment): JsonResponse
    {
        $equipment->load('rig:id,name,code,location_id', 'rig.location:id,name');
        return $this->success($equipment);
    }

    /** PUT /api/equipments/{equipment} */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse
    {
        $equipment->update($request->validated());
        return $this->success($equipment->fresh('rig:id,name,code'), 'Equipment updated');
    }

    /** DELETE /api/equipments/{equipment} */
    public function destroy(Equipment $equipment): JsonResponse
    {
        $equipment->delete();
        return $this->success(null, 'Equipment deleted');
    }

    /** GET /api/equipments/stats */
    public function stats(): JsonResponse
    {
        return $this->success([
            'total'      => Equipment::count('name'),
            'deployed'   => Equipment::whereNotNull('current_rig_id')->count(),
            'unassigned' => Equipment::whereNull('current_rig_id')->count(),
        ]);
    }
}
