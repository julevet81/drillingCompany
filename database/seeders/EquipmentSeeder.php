<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Rig;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $rig1 = Rig::where('code', 'RIG-001')->first();
        $rig2 = Rig::where('code', 'RIG-002')->first();
        $rig3 = Rig::where('code', 'RIG-003')->first();
        $rig4 = Rig::where('code', 'RIG-004')->first();
        $rig5 = Rig::where('code', 'RIG-005')->first();

        $equipments = [
            // RIG-001 equipment (matching UI screenshots)
            [
                'current_rig_id' => $rig1?->id,
                'name'           => 'Drilling Rig',
                'marque'         => 'GEFCO T40',
                'serial_number'  => '1635-874-39',
            ],
            [
                'current_rig_id' => $rig1?->id,
                'name'           => 'Generator',
                'marque'         => 'Cummins',
                'serial_number'  => '120-KVA-01',
            ],
            [
                'current_rig_id' => $rig1?->id,
                'name'           => 'Water Tank',
                'marque'         => 'Volvo',
                'serial_number'  => '10547-213-39',
            ],

            // RIG-002 equipment
            [
                'current_rig_id' => $rig2?->id,
                'name'           => 'Excavator',
                'marque'         => 'Caterpillar',
                'serial_number'  => '0427-855-39',
            ],
            [
                'current_rig_id' => $rig2?->id,
                'name'           => 'Compressor',
                'marque'         => 'Atlas Copco',
                'serial_number'  => 'CP-2024-006',
            ],

            // RIG-003 equipment
            [
                'current_rig_id' => $rig3?->id,
                'name'           => 'Truck',
                'marque'         => 'Mercedes',
                'serial_number'  => 'TRK-2023-04',
            ],

            // RIG-004 equipment
            [
                'current_rig_id' => $rig4?->id,
                'name'           => 'Crane',
                'marque'         => 'Liebherr',
                'serial_number'  => 'GM-2023-045',
            ],

            // RIG-005 equipment
            [
                'current_rig_id' => $rig5?->id,
                'name'           => 'Forklift',
                'marque'         => 'Toyota',
                'serial_number'  => 'TY-2024-011',
            ],

            // Unassigned equipment (spare pool)
            [
                'current_rig_id' => null,
                'name'           => 'Generator',
                'marque'         => 'Perkins',
                'serial_number'  => 'PKN-2023-002',
            ],
            [
                'current_rig_id' => null,
                'name'           => 'Water Pump',
                'marque'         => 'Grundfos',
                'serial_number'  => 'GF-2022-017',
            ],
        ];

        foreach ($equipments as $eq) {
            Equipment::firstOrCreate(
                ['serial_number' => $eq['serial_number']],
                $eq
            );
        }

        $this->command->info('  ✅ Equipment seeded (10 items across rigs)');
    }
}
