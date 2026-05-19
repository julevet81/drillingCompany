<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Location::withCount('rigs')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'state'     => ['nullable', 'string', 'max:100'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        return $this->created(Location::create($data), 'Location created');
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'state'     => ['nullable', 'string', 'max:100'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $location->update($data);
        return $this->success($location, 'Location updated');
    }

    public function destroy(Location $location): JsonResponse
    {
        if ($location->rigs()->exists()) {
            return $this->error('Cannot delete a location that has rigs assigned.', 422);
        }
        $location->delete();
        return $this->success(null, 'Location deleted');
    }
}
