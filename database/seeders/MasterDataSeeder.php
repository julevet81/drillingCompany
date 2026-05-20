<?php

namespace Database\Seeders;

use App\Models\MaterialType;
use App\Models\Position;
use App\Models\ToolType;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── BHA Tool Types ───────────────────────────────────────────
        $toolTypes = [
            'Outil', 'Raccord',
            'Masse Tige 1', 'Masse Tige 2', 'Masse Tige 3', 'Masse Tige 4',
            'Tige', 'Kelly',
        ];
        foreach ($toolTypes as $name) {
            ToolType::firstOrCreate(['name' => $name]);
        }

        // ── Employee Positions ───────────────────────────────────────
        $positions = [
            'Tool Pusher', 'Driller', 'Derrickman', 'Motorman',
            'Floorhand', 'Roughneck', 'Company Man', 'Mud Engineer',
            'Rig Superintendent', 'Safety Officer', 'Electrician', 'Mechanic',
        ];
        foreach ($positions as $name) {
            Position::firstOrCreate(['name' => $name]);
        }

        // ── Material Types ───────────────────────────────────────────
        $materials = [
            ['name' => 'Diesel Fuel', 'unit' => 'L'],
            ['name' => 'Bentonite',   'unit' => 'kg'],
            ['name' => 'Barite',      'unit' => 'kg'],
            ['name' => 'Cement',      'unit' => 'kg'],
            ['name' => 'Water',       'unit' => 'L'],
            ['name' => 'Chemicals',   'unit' => 'kg'],
        ];
        foreach ($materials as $m) {
            MaterialType::firstOrCreate(['name' => $m['name']], ['unit' => $m['unit']]);
        }

        $this->command->info('  ✅ Master data seeded (tool types, positions, material types)');
    }
}
