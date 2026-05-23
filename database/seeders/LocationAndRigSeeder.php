<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Rig;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocationAndRigSeeder extends Seeder
{
    public function run(): void
    {
        // ── Locations (real Algerian oil fields) ─────────────────────
        $locations = [
            ['name' => 'Hassi Messaoud', 'state' => 'Ouargla',     'latitude' => 31.6997, 'longitude' => 6.0622],
            ['name' => 'Berkine Basin',  'state' => 'Illizi',       'latitude' => 30.2167, 'longitude' => 8.4833],
            ['name' => 'In Amenas',      'state' => 'Illizi',       'latitude' => 28.0508, 'longitude' => 9.5522],
            ['name' => 'Ouargla',        'state' => 'Ouargla',      'latitude' => 31.9539, 'longitude' => 5.3247],
            ['name' => 'Illizi Basin',   'state' => 'Illizi',       'latitude' => 26.5000, 'longitude' => 8.4667],
            ['name' => 'Rhourd Nouss',   'state' => 'Ouargla',      'latitude' => 30.9333, 'longitude' => 7.9500],
            ['name' => 'In Salah',       'state' => 'Tamanrasset',  'latitude' => 27.1960, 'longitude' => 2.4797],
        ];

        $locs = [];
        foreach ($locations as $loc) {
            $locs[$loc['name']] = Location::firstOrCreate(['name' => $loc['name']], $loc);
        }

        // ── Users for managers ───────────────────────────────────────
        $karim  = User::where('email', 'karim.messaoudi@oms.dz')->first();
        $omar   = User::where('email', 'omar.saidi@oms.dz')->first();
        $yacine = User::where('email', 'yacine.boudiaf@oms.dz')->first();

        // ── Rigs (matching UI cards) ─────────────────────────────────
        $rigs = [
            [
                'name'           => 'HMD-North-12',
                'code'           => 'RIG-001',
                'location_id'    => $locs['Hassi Messaoud']->id,
                'manager_id'     => $karim?->id,
                'status'         => 'drilling',
                'current_depth'  => 3850.00,
                'target_depth'   => 4200.00,
                'drilling_phase' => 'Drilling 8½"',
                'start_date'     => '2026-04-15',
                'end_date'       => '2026-06-10',
            ],
            [
                'name'           => 'BRK-Basin-07',
                'code'           => 'RIG-002',
                'location_id'    => $locs['Berkine Basin']->id,
                'manager_id'     => $omar?->id,
                'status'         => 'drilling',
                'current_depth'  => 2340.00,
                'target_depth'   => 3500.00,
                'drilling_phase' => 'Drilling 12¼"',
                'start_date'     => '2026-03-01',
                'end_date'       => '2026-07-15',
            ],
            [
                'name'           => 'IAM-Field-15',
                'code'           => 'RIG-003',
                'location_id'    => $locs['In Amenas']->id,
                'manager_id'     => $yacine?->id,
                'status'         => 'casing',
                'current_depth'  => 1920.00,
                'target_depth'   => 2800.00,
                'drilling_phase' => 'Casing 13⅝"',
                'start_date'     => '2026-02-10',
                'end_date'       => '2026-08-20',
            ],
            [
                'name'           => 'OGS-South-03',
                'code'           => 'RIG-004',
                'location_id'    => $locs['Ouargla']->id,
                'manager_id'     => null,
                'status'         => 'stopped',
                'current_depth'  => 4100.00,
                'target_depth'   => 4100.00,
                'drilling_phase' => 'Completion',
                'start_date'     => '2025-11-01',
                'end_date'       => '2026-05-01',
            ],
            [
                'name'           => 'ILZ-Basin-09',
                'code'           => 'RIG-005',
                'location_id'    => $locs['Illizi Basin']->id,
                'manager_id'     => null,
                'status'         => 'drilling',
                'current_depth'  => 1560.00,
                'target_depth'   => 3200.00,
                'drilling_phase' => 'Drilling 17½"',
                'start_date'     => '2026-04-01',
                'end_date'       => '2026-09-30',
            ],
            [
                'name'           => 'RNS-Prod-18',
                'code'           => 'RIG-006',
                'location_id'    => $locs['Rhourd Nouss']->id,
                'manager_id'     => null,
                'status'         => 'fishing',
                'current_depth'  => 2890.00,
                'target_depth'   => 3600.00,
                'drilling_phase' => 'Drilling 8½"',
                'start_date'     => '2026-01-15',
                'end_date'       => '2026-06-30',
            ],
            [
                'name'           => 'ISH-Gas-22',
                'code'           => 'RIG-007',
                'location_id'    => $locs['In Salah']->id,
                'manager_id'     => null,
                'status'         => 'dtm',
                'current_depth'  => 4500.00,
                'target_depth'   => 4500.00,
                'drilling_phase' => 'Production',
                'start_date'     => '2025-09-01',
                'end_date'       => '2026-04-01',
            ],
            [
                'name'           => 'HMD-East-05',
                'code'           => 'RIG-008',
                'location_id'    => $locs['Hassi Messaoud']->id,
                'manager_id'     => null,
                'status'         => 'developing',
                'current_depth'  => 1200.00,
                'target_depth'   => 3000.00,
                'drilling_phase' => 'Drilling 26"',
                'start_date'     => '2026-05-01',
                'end_date'       => '2026-12-31',
            ],
        ];

        foreach ($rigs as $rig) {
            Rig::firstOrCreate(['code' => $rig['code']], $rig);
        }

        $this->command->info('  ✅ Locations & Rigs seeded (7 locations, 8 rigs)');
    }
}
