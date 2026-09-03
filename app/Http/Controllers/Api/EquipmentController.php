<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends BaseApiController
{
    /** GET /api/equipments */
    public function index(Request $request): JsonResponse
    {
        $query = Equipment::with('rig:id,name,code');

        if ($allowedRigIds = $request->attributes->get('allowed_rig_ids')) {
            $query->whereIn('current_rig_id', $allowedRigIds);
        }

        if ($request->filled('rig_id')) {
            $rigId = $request->integer('rig_id');

            $query->where(function ($query) use ($rigId) {
                $query->where('current_rig_id', $rigId)
                    // Older reports may reference equipment whose current rig
                    // was never persisted. Keep that legacy data visible.
                    ->orWhere(function ($query) use ($rigId) {
                        $query->whereNull('current_rig_id')
                            ->whereHas(
                                'dailyReportEntries.report',
                                fn ($query) => $query->where('rig_id', $rigId)
                            );
                    });
            });
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('name', 'like', "%$s%")
                ->orWhere('serial_number', 'like', "%$s%")
                ->orWhere('marque', 'like', "%$s%"));
        }

        // This endpoint is used to populate equipment selectors. Returning a
        // partial page makes available equipment disappear when the client
        // sends a small `per_page` value, so keep all matching equipment in a
        // single response.
        $perPage = max(1, (clone $query)->count());

        return $this->paginated($query->latest()->paginate($perPage));
    }

    /** POST /api/equipments */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('uploads/equipments'), $filename);

            $data['photo'] = 'uploads/equipments/' . $filename;
        }

        $equipment = Equipment::create($data);
        return $this->created($equipment->refresh()->load('rig:id,name,code'), 'Equipment added');
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
        $data = $request->validated();
        unset($data['photo'], $data['image'], $data['avatar'], $data['file']);

        if ($request->hasFile('photo')) {

            if ($equipment->photo && file_exists(public_path($equipment->photo))) {
                unlink(public_path($equipment->photo));
            }

            $file = $request->file('photo');

            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('uploads/equipments'), $filename);

            $data['photo'] = 'uploads/equipments/' . $filename;
        }

        $equipment->update($data);
        return $this->success($equipment->refresh()->load('rig:id,name,code'), 'Equipment updated');
    }

    /** DELETE /api/equipments/{equipment} */
    public function destroy(Equipment $equipment): JsonResponse
    {
        if ($equipment->photo && file_exists(public_path($equipment->photo))) {
            unlink(public_path($equipment->photo));
        }


        $equipment->delete();
        return $this->success(null, 'Equipment deleted');
    }

    /** DELETE /api/equipments/{equipment}/photo */
    public function deletePhoto(Equipment $equipment): JsonResponse
    {
        if (!$equipment->photo) {
            return $this->error('No photo to delete', 404);
        }

        if (file_exists(public_path($equipment->photo))) {
            unlink(public_path($equipment->photo));
        }

        $equipment->update([
            'photo' => null
        ]);

        return $this->success(null, 'Photo deleted');
    }

    /** GET /api/equipments/stats */
    public function stats(): JsonResponse
    {
        return $this->success([
            'total'       => Equipment::count(),
            'deployed'    => Equipment::whereNotNull('current_rig_id')->count(),
            'unassigned'  => Equipment::whereNull('current_rig_id')->count(),
            'operational' => Equipment::where('status', 'Operational')->count(),
            'maintenance' => Equipment::where('status', 'Maintenance')->count(),
        ]);
    }
}
