<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Rig;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class EmployeeAndShiftSeeder extends Seeder
{
    public function run(): void
    {
        // Helper to get position id by name
        $pos = fn (string $name): int => Position::where('name', $name)->value('id');

        // ── Employees ────────────────────────────────────────────────
        $employees = [
            ['full_name' => 'Ahmed Benali',    'position_id' => $pos('Tool Pusher')],
            ['full_name' => 'Karim Messaoudi', 'position_id' => $pos('Driller')],
            ['full_name' => 'Omar Saidi',      'position_id' => $pos('Derrickman')],
            ['full_name' => 'Yacine Boudiaf',  'position_id' => $pos('Motorman')],
            ['full_name' => 'Farid Zerrouki',  'position_id' => $pos('Floorhand')],
            ['full_name' => 'Tarek Hamdi',     'position_id' => $pos('Floorhand')],
            ['full_name' => 'Mourad Belkacem', 'position_id' => $pos('Mud Engineer')],
            ['full_name' => 'Sofiane Rais',    'position_id' => $pos('Safety Officer')],
            ['full_name' => 'Bilal Ouali',     'position_id' => $pos('Electrician')],
            ['full_name' => 'Nabil Gaci',      'position_id' => $pos('Mechanic')],
            ['full_name' => 'Rachid Benmoussa','position_id' => $pos('Roughneck')],
            ['full_name' => 'Djamel Saidani',  'position_id' => $pos('Roughneck')],
        ];

        $createdEmployees = [];
        foreach ($employees as $emp) {
            $createdEmployees[] = Employee::firstOrCreate(
                ['full_name' => $emp['full_name']],
                $emp
            );
        }

        // ── Shifts for active rigs ────────────────────────────────────
        // RIG-001 (HMD-North-12) — Day & Night shift today
        $rig1 = Rig::where('code', 'RIG-001')->first();
        $rig2 = Rig::where('code', 'RIG-002')->first();
        $rig3 = Rig::where('code', 'RIG-003')->first();
        $rig5 = Rig::where('code', 'RIG-005')->first();

        $today = today()->toDateString();

        if ($rig1) {
            $dayShift1 = Shift::firstOrCreate(
                ['date' => $today, 'periode' => 'day', 'rig_id' => $rig1->id]
            );
            // Attach employees to shift
            $dayShift1->employees()->syncWithoutDetaching([
                $createdEmployees[0]->id => ['function' => 'Tool Pusher',  'status' => 'onsite'],
                $createdEmployees[1]->id => ['function' => 'Driller',      'status' => 'onsite'],
                $createdEmployees[2]->id => ['function' => 'Derrickman',   'status' => 'onsite'],
                $createdEmployees[3]->id => ['function' => 'Motorman',     'status' => 'onsite'],
                $createdEmployees[4]->id => ['function' => 'Floorhand',    'status' => 'onsite'],
                $createdEmployees[5]->id => ['function' => 'Floorhand',    'status' => 'onBase'],
            ]);

            Shift::firstOrCreate(
                ['date' => $today, 'periode' => 'night', 'rig_id' => $rig1->id]
            )->employees()->syncWithoutDetaching([
                $createdEmployees[6]->id => ['function' => 'Mud Engineer',    'status' => 'onsite'],
                $createdEmployees[7]->id => ['function' => 'Safety Officer',  'status' => 'onsite'],
            ]);
        }

        if ($rig2) {
            Shift::firstOrCreate(
                ['date' => $today, 'periode' => 'day', 'rig_id' => $rig2->id]
            )->employees()->syncWithoutDetaching([
                $createdEmployees[8]->id  => ['function' => 'Electrician', 'status' => 'onsite'],
                $createdEmployees[9]->id  => ['function' => 'Mechanic',    'status' => 'onsite'],
                $createdEmployees[10]->id => ['function' => 'Roughneck',   'status' => 'onsite'],
            ]);
        }

        if ($rig3) {
            Shift::firstOrCreate(
                ['date' => $today, 'periode' => 'day', 'rig_id' => $rig3->id]
            )->employees()->syncWithoutDetaching([
                $createdEmployees[11]->id => ['function' => 'Roughneck', 'status' => 'onLeave'],
            ]);
        }

        $this->command->info('  ✅ Employees & Shifts seeded (12 employees, shifts for active rigs)');
    }
}
