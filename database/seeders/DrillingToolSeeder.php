<?php

namespace Database\Seeders;

use App\Models\DrillingTool;
use App\Models\Rig;
use App\Models\ToolType;
use Illuminate\Database\Seeder;

class DrillingToolSeeder extends Seeder
{
    public function run(): void
    {
        // Helper to get ToolType id
        $type = fn (string $name): ?int => ToolType::where('name', $name)->value('id');

        $rig1 = Rig::where('code', 'RIG-001')->first();
        $rig2 = Rig::where('code', 'RIG-002')->first();
        $rig3 = Rig::where('code', 'RIG-003')->first();

        // ── RIG-001 BHA (HMD-North-12) — matches UI screenshot exactly ──
        // Elément     | Diam. Ext | Nombre | Long. (M.L)
        // Outil        12"1/4       1        0.3
        // Raccord      6" 5/8       2        0.6
        // Masse Tige 1  8"           -        9.4
        // Masse Tige 2  8"           1        9.23
        // Tige         3.5"         12       112.79
        // Kelly         -            -        7
        if ($rig1) {
            $bha1 = [
                ['tool_type_id' => $type('Outil'),       'external_diameter' => '12"1/4', 'total_quantity' => 1,  'unit_length' => 0.30,   'name' => 'PDC Bit 12"1/4'],
                ['tool_type_id' => $type('Raccord'),     'external_diameter' => '6" 5/8', 'total_quantity' => 2,  'unit_length' => 0.30,   'name' => 'Crossover Sub'],
                ['tool_type_id' => $type('Masse Tige 1'),'external_diameter' => '8"',     'total_quantity' => 1,  'unit_length' => 9.40,   'name' => 'Drill Collar 8"'],
                ['tool_type_id' => $type('Masse Tige 2'),'external_diameter' => '8"',     'total_quantity' => 1,  'unit_length' => 9.23,   'name' => 'Drill Collar 8" #2'],
                ['tool_type_id' => $type('Tige'),        'external_diameter' => '3.5"',   'total_quantity' => 12, 'unit_length' => 9.40,   'name' => 'Drill Pipe 3.5"'],
                ['tool_type_id' => $type('Kelly'),       'external_diameter' => null,      'total_quantity' => 1,  'unit_length' => 7.00,   'name' => 'Kelly Square'],
            ];

            foreach ($bha1 as $tool) {
                DrillingTool::firstOrCreate(
                    ['rig_id' => $rig1->id, 'tool_type_id' => $tool['tool_type_id'], 'name' => $tool['name']],
                    array_merge($tool, ['rig_id' => $rig1->id, 'status' => 'active'])
                );
            }
        }

        // ── RIG-002 BHA (BRK-Basin-07) ──────────────────────────────
        if ($rig2) {
            $bha2 = [
                ['tool_type_id' => $type('Outil'),       'external_diameter' => '12"1/4', 'total_quantity' => 1,  'unit_length' => 0.30,  'name' => 'Tricone Bit 12"1/4'],
                ['tool_type_id' => $type('Raccord'),     'external_diameter' => '6" 5/8', 'total_quantity' => 1,  'unit_length' => 0.30,  'name' => 'Crossover Sub'],
                ['tool_type_id' => $type('Masse Tige 1'),'external_diameter' => '8"',     'total_quantity' => 2,  'unit_length' => 9.23,  'name' => 'Drill Collar 8"'],
                ['tool_type_id' => $type('Tige'),        'external_diameter' => '5"',     'total_quantity' => 15, 'unit_length' => 9.50,  'name' => 'Heavy Weight Drill Pipe 5"'],
                ['tool_type_id' => $type('Kelly'),       'external_diameter' => null,      'total_quantity' => 1,  'unit_length' => 7.00,  'name' => 'Kelly Hex'],
            ];

            foreach ($bha2 as $tool) {
                DrillingTool::firstOrCreate(
                    ['rig_id' => $rig2->id, 'tool_type_id' => $tool['tool_type_id'], 'name' => $tool['name']],
                    array_merge($tool, ['rig_id' => $rig2->id, 'status' => 'active'])
                );
            }
        }

        // ── RIG-003 BHA (IAM-Field-15) ──────────────────────────────
        if ($rig3) {
            $bha3 = [
                ['tool_type_id' => $type('Outil'),       'external_diameter' => '17"1/2', 'total_quantity' => 1,  'unit_length' => 0.45,  'name' => 'PDC Bit 17"1/2'],
                ['tool_type_id' => $type('Raccord'),     'external_diameter' => '8"',     'total_quantity' => 2,  'unit_length' => 0.30,  'name' => 'Stabilizer Sub'],
                ['tool_type_id' => $type('Masse Tige 1'),'external_diameter' => '9"',     'total_quantity' => 3,  'unit_length' => 9.00,  'name' => 'Drill Collar 9"'],
                ['tool_type_id' => $type('Tige'),        'external_diameter' => '5"',     'total_quantity' => 10, 'unit_length' => 9.50,  'name' => 'Drill Pipe 5"'],
            ];

            foreach ($bha3 as $tool) {
                DrillingTool::firstOrCreate(
                    ['rig_id' => $rig3->id, 'tool_type_id' => $tool['tool_type_id'], 'name' => $tool['name']],
                    array_merge($tool, ['rig_id' => $rig3->id, 'status' => 'active'])
                );
            }
        }

        $this->command->info('  ✅ Drilling Tools (BHA) seeded for 3 rigs');
    }
}
