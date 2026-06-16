<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;

class EmployeeAndShiftSeeder extends Seeder
{
    public function run(): void
    {
        $pos = fn(string $name): int => Position::where('name', $name)->value('id');

        $employees = [
            ['full_name' => 'Ahmed Benali',     'position_id' => $pos('Tool Pusher')],
            ['full_name' => 'Karim Messaoudi',  'position_id' => $pos('Driller')],
            ['full_name' => 'Omar Saidi',        'position_id' => $pos('Derrickman')],
            ['full_name' => 'Yacine Boudiaf',   'position_id' => $pos('Motorman')],
            ['full_name' => 'Farid Zerrouki',   'position_id' => $pos('Floorhand')],
            ['full_name' => 'Tarek Hamdi',      'position_id' => $pos('Floorhand')],
            ['full_name' => 'Mourad Belkacem',  'position_id' => $pos('Mud Engineer')],
            ['full_name' => 'Sofiane Rais',     'position_id' => $pos('Safety Officer')],
            ['full_name' => 'Bilal Ouali',      'position_id' => $pos('Electrician')],
            ['full_name' => 'Nabil Gaci',       'position_id' => $pos('Mechanic')],
            ['full_name' => 'Rachid Benmoussa', 'position_id' => $pos('Roughneck')],
            ['full_name' => 'Djamel Saidani',   'position_id' => $pos('Roughneck')],
        ];

        foreach ($employees as $emp) {
            Employee::firstOrCreate(['full_name' => $emp['full_name']], $emp);
        }

        // الـ shifts تُنشأ داخل DailyReportSeeder مع كل تقرير
        $this->command->info('  ✅ Employees seeded (12 employees) — shifts will be created with daily reports');
    }
}
