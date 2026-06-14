<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Equipment;
use App\Models\Rig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TvDisplayController extends BaseApiController
{
    /**
     * GET /api/tv-display
     *
     * Single endpoint that returns ALL data needed for the TV Display screen:
     *  - Active rigs list with job type, equipment, and on-site crew
     *  - Crew on base
     *  - Crew off
     *  - Equipment management (in base + onset last week)
     *  - Rig locations for the map
     */
    public function index(): JsonResponse
    {
        try {
            $data = Cache::remember('tv_display', 60, function () {
                return [
                    'rigs'               => $this->getActiveRigs(),
                    'crew_on_base'       => $this->getCrewByStatus('onBase'),
                    'crew_off'           => $this->getCrewByStatus('onLeave'),
                    'equipment_in_base'  => $this->getEquipmentInBase(),
                    'equipment_last_week' => $this->getEquipmentOnsetLastWeek(),
                    'map_locations'      => $this->getMapLocations(),
                    'generated_at'       => now()->toISOString(),
                ];
            });

            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error(
                config('app.debug') ? $e->getMessage() : 'Failed to load TV display data',
                500
            );
        }
    }

    /**
     * GET /api/tv-display/rigs
     * Only the rigs section (for partial refresh)
     */
    public function rigs(): JsonResponse
    {
        try {
            return $this->success($this->getActiveRigs());
        } catch (\Exception $e) {
            return $this->error(
                config('app.debug') ? $e->getMessage() : 'Failed to load rigs',
                500
            );
        }
    }

    /**
     * GET /api/tv-display/crew
     * Only crew sections (on-site, on-base, off)
     */
    public function crew(): JsonResponse
    {
        try {
            return $this->success([
                'on_site'   => $this->getCrewByStatus('onsite'),
                'on_base'   => $this->getCrewByStatus('onBase'),
                'off'       => $this->getCrewByStatus('onLeave'),
                'counts'    => [
                    'onsite'  => $this->countCrewByStatus('onsite'),
                    'on_base' => $this->countCrewByStatus('onBase'),
                    'off'     => $this->countCrewByStatus('onLeave'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->error(
                config('app.debug') ? $e->getMessage() : 'Failed to load crew data',
                500
            );
        }
    }

    /**
     * GET /api/tv-display/equipment
     * Only equipment section
     */
    public function equipment(): JsonResponse
    {
        try {
            return $this->success([
                'in_base'   => $this->getEquipmentInBase(),
                'last_week' => $this->getEquipmentOnsetLastWeek(),
            ]);
        } catch (\Exception $e) {
            return $this->error(
                config('app.debug') ? $e->getMessage() : 'Failed to load equipment data',
                500
            );
        }
    }

    // ─── Private builders ─────────────────────────────────────────────────────

    /**
     * Active rigs with their drilling phase (job type), on-site equipment, and crew.
     * Matches the left table in the TV Display screenshot.
     */
    private function getActiveRigs(): array
    {
        $activeStatuses = ['active', 'casing', 'fishing', 'maintenance'];

        $rigs = Rig::with([
            'location:id,name,state,latitude,longitude',
            // Today's shifts with employees
            'shifts' => fn($q) => $q
                ->whereDate('date', today())
                ->with([
                    'employees:id,full_name,photo,position_id',
                    'employees.position:id,name',
                ]),
            // Equipment currently at this rig
            'equipments:id,current_rig_id,name,marque,serial_number',
        ])
            ->whereIn('status', $activeStatuses)
            ->orderBy('name')
            ->get();

        return $rigs->map(function (Rig $rig) {
            // Flatten crew from all today's shifts
            $crew = collect();
            foreach ($rig->shifts as $shift) {
                foreach ($shift->employees as $emp) {
                    // Only onsite employees
                    if ($emp->pivot->status === 'onsite') {
                        $crew->push([
                            'id'       => $emp->id,
                            'name'     => $this->shortName($emp->full_name),
                            'full_name' => $emp->full_name,
                            'position' => $emp->position?->name,
                            'photo'    => $emp->photo
                                ? asset($emp->photo)
                                : null,
                            'shift'    => $shift->periode,
                        ]);
                    }
                }
            }

            return [
                'id'             => $rig->id,
                'name'           => $rig->name,
                'code'           => $rig->code,
                'location'       => $rig->location?->name,
                'state'          => $rig->location?->state,
                'status'         => $rig->status,
                'job_type'       => $this->formatJobType($rig->drilling_phase, $rig->status),
                'current_depth'  => (float) $rig->current_depth,
                'target_depth'   => (float) $rig->target_depth,
                'progress'       => $rig->progress_percentage,
                'equipment'      => $rig->equipments->map(fn($e) => [
                    'id'            => $e->id,
                    'name'          => $e->name,
                    'marque'        => $e->marque,
                    'serial_number' => $e->serial_number,
                ])->values(),
                'crew_onsite'    => $crew->unique('id')->values(),
                'crew_count'     => $crew->unique('id')->count(),
            ];
        })->values()->toArray();
    }

    /**
     * Crew filtered by shift status for today.
     * Used for CREW ON BASE and CREW OFF panels.
     */
    private function getCrewByStatus(string $status): array
    {
        $employees = Employee::whereHas('shifts', function ($q) use ($status) {
            $q->whereDate('shifts.date', today())
                ->where('employee_shifts.status', $status);
        })
            ->with([
                'position:id,name',
                'shifts' => fn($q) => $q
                    ->whereDate('date', today())
                    ->with('rig:id,name,code'),
            ])
            ->get();

        return $employees->map(fn($emp) => [
            'id'       => $emp->id,
            'name'     => $this->shortName($emp->full_name),
            'full_name' => $emp->full_name,
            'position' => $emp->position?->name,
            'photo'    => $emp->photo ? asset($emp->photo) : null,
            'rig'      => $emp->shifts->first()?->rig?->code,
        ])->values()->toArray();
    }

    private function countCrewByStatus(string $status): int
    {
        return EmployeeShift::where('status', $status)
            ->whereHas('shift', fn($q) => $q->whereDate('date', today()))
            ->count();
    }

    /**
     * Equipment with no rig assigned (in the base pool).
     */
    private function getEquipmentInBase(): array
    {
        return Equipment::whereNull('current_rig_id')
            ->get()
            ->map(fn($e) => [
                'id'            => $e->id,
                'name'          => $e->name,
                'marque'        => $e->marque,
                'serial_number' => $e->serial_number,
                'status'        => 'In Base',
            ])
            ->values()
            ->toArray();
    }

    /**
     * Equipment that was moved to a rig in the last 7 days.
     * Approximated by recently updated equipment with a rig assigned.
     */
    private function getEquipmentOnsetLastWeek(): array
    {
        return Equipment::whereNotNull('current_rig_id')
            ->where('updated_at', '>=', now()->subDays(7))
            ->with('rig:id,name,code')
            ->get()
            ->map(fn($e) => [
                'id'            => $e->id,
                'name'          => $e->name,
                'marque'        => $e->marque,
                'serial_number' => $e->serial_number,
                'rig_code'      => $e->rig?->code,
                'rig_name'      => $e->rig?->name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Rig coordinates for the map panel.
     */
    private function getMapLocations(): array
    {
        return Rig::with('location:id,name,latitude,longitude')
            ->whereHas(
                'location',
                fn($q) => $q
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
            )
            ->whereIn('status', ['active', 'casing', 'fishing', 'maintenance', 'paused'])
            ->get()
            ->map(fn($rig) => [
                'id'        => $rig->id,
                'name'      => $rig->name,
                'code'      => $rig->code,
                'status'    => $rig->status,
                'latitude'  => (float) $rig->location->latitude,
                'longitude' => (float) $rig->location->longitude,
                'location'  => $rig->location->name,
            ])
            ->values()
            ->toArray();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Convert "Ahmed Benali" → "Ahmed B."
     * Short name format used in TV Display avatars.
     */
    private function shortName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        if (count($parts) < 2) return $fullName;

        return $parts[0] . ' ' . strtoupper(substr($parts[1], 0, 1)) . '.';
    }

    /**
     * Format the drilling phase into a readable job type label.
     * e.g. "Drilling 8½"" → "½"DRILLING / 8"
     * Matches the UI format in the TV Display screenshot.
     */
    private function formatJobType(?string $drillingPhase, string $status): string
    {
        if (!$drillingPhase) {
            return match ($status) {
                'active'      => 'DRILLING',
                'casing'      => 'CASING',
                'fishing'     => 'FISHING',
                'maintenance' => 'MAINTENANCE',
                'paused'      => 'STAND BY',
                'dtm'         => 'DTM',
                default       => strtoupper($status),
            };
        }

        // "Drilling 8½"" → "½"DRILLING / 8" (TV display format)
        // or simply return as uppercase label
        return strtoupper($drillingPhase);
    }
}
