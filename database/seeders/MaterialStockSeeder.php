<?php

namespace Database\Seeders;

use App\Models\MaterialType;
use App\Models\Rig;
use App\Models\RigMaterial;
use Illuminate\Database\Seeder;

class MaterialStockSeeder extends Seeder
{
    public function run(): void
    {
        $diesel    = MaterialType::where('name', 'Diesel Fuel')->first();
        $bentonite = MaterialType::where('name', 'Bentonite')->first();
        $barite    = MaterialType::where('name', 'Barite')->first();
        $cement    = MaterialType::where('name', 'Cement')->first();
        $water     = MaterialType::where('name', 'Water')->first();
        $chemicals = MaterialType::where('name', 'Chemicals')->first();

        // Fuel levels matching the Fuel Tracking UI (RIG-001 to RIG-005)
        // RIG-001: 12,500 / 20,000 L = 63%
        // RIG-002:  8,200 / 15,000 L = 55%
        // RIG-003:  5,400 / 15,000 L = 36%
        // RIG-004:  2,100 / 12,000 L = 18% ⚠ LOW
        // RIG-005:  9,800 / 18,000 L = 54%

        $fuelData = [
            'RIG-001' => ['quantity' => 12500, 'capacity' => 20000],
            'RIG-002' => ['quantity' => 8200,  'capacity' => 15000],
            'RIG-003' => ['quantity' => 5400,  'capacity' => 15000],
            'RIG-004' => ['quantity' => 2100,  'capacity' => 12000],
            'RIG-005' => ['quantity' => 9800,  'capacity' => 18000],
            'RIG-006' => ['quantity' => 1750,  'capacity' => 15000],
            'RIG-007' => ['quantity' => 3000,  'capacity' => 12000],
            'RIG-008' => ['quantity' => 4500,  'capacity' => 15000],
        ];

        foreach ($fuelData as $code => $stock) {
            $rig = Rig::where('code', $code)->first();
            if (!$rig || !$diesel) continue;

            RigMaterial::updateOrCreate(
                ['rig_id' => $rig->id, 'material_type_id' => $diesel->id],
                $stock
            );
        }

        // Drilling materials for active rigs
        $drillingMaterials = [
            // RIG-001 (HMD-North-12) — matches screenshot sidebar
            'RIG-001' => [
                ['type' => $bentonite, 'quantity' => 950,  'capacity' => 5000],
                ['type' => $barite,    'quantity' => 420,  'capacity' => 3000],
                ['type' => $cement,    'quantity' => 3200, 'capacity' => 10000],
                ['type' => $water,     'quantity' => 45000,'capacity' => 80000],
                ['type' => $chemicals, 'quantity' => 850,  'capacity' => 2000],
            ],
            // RIG-002 (BRK-Basin-07)
            'RIG-002' => [
                ['type' => $bentonite, 'quantity' => 1200, 'capacity' => 5000],
                ['type' => $barite,    'quantity' => 750,  'capacity' => 3000],
                ['type' => $water,     'quantity' => 38000,'capacity' => 80000],
            ],
            // RIG-003 (IAM-Field-15)
            'RIG-003' => [
                ['type' => $bentonite, 'quantity' => 200,  'capacity' => 5000],
                ['type' => $cement,    'quantity' => 8500, 'capacity' => 10000],
                ['type' => $water,     'quantity' => 52000,'capacity' => 80000],
            ],
            // RIG-005 (ILZ-Basin-09)
            'RIG-005' => [
                ['type' => $bentonite, 'quantity' => 2100, 'capacity' => 5000],
                ['type' => $barite,    'quantity' => 1800, 'capacity' => 3000],
                ['type' => $water,     'quantity' => 60000,'capacity' => 80000],
            ],
        ];

        foreach ($drillingMaterials as $code => $materials) {
            $rig = Rig::where('code', $code)->first();
            if (!$rig) continue;

            foreach ($materials as $m) {
                if (!$m['type']) continue;
                RigMaterial::updateOrCreate(
                    ['rig_id' => $rig->id, 'material_type_id' => $m['type']->id],
                    ['quantity' => $m['quantity'], 'capacity' => $m['capacity']]
                );
            }
        }

        $this->command->info('  ✅ Material stock seeded (fuel + drilling materials for all rigs)');
    }
}
