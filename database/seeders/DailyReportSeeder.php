<?php

namespace Database\Seeders;

use App\Models\DailyReport;
use App\Models\DailyReportEquipment;
use App\Models\DailyReportTool;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\DrillingTool;
use App\Models\MaterialLog;
use App\Models\Rig;
use App\Models\RigMaterial;
use App\Models\MaterialType;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DailyReportSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'ahmed.benali@oms.dz')->first();
        $karim = User::where('email', 'karim.messaoudi@oms.dz')->first();
        $omar  = User::where('email', 'omar.saidi@oms.dz')->first();

        if (!$admin) {
            $this->command->warn('  ⚠ No admin user found, skipping DailyReportSeeder');
            return;
        }

        $diesel = MaterialType::where('name', 'Diesel Fuel')->first();

        // تجميع الموظفين مسبقاً
        $allEmployees = Employee::all();

        // توزيع الموظفين حسب الـ rig
        $employeeGroups = [
            'RIG-001' => [
                'day'   => [
                    ['name' => 'Ahmed Benali',    'function' => 'Tool Pusher',   'status' => 'onsite'],
                    ['name' => 'Karim Messaoudi', 'function' => 'Driller',       'status' => 'onsite'],
                    ['name' => 'Omar Saidi',       'function' => 'Derrickman',    'status' => 'onsite'],
                    ['name' => 'Yacine Boudiaf',  'function' => 'Motorman',      'status' => 'onsite'],
                    ['name' => 'Farid Zerrouki',  'function' => 'Floorhand',     'status' => 'onsite'],
                    ['name' => 'Tarek Hamdi',     'function' => 'Floorhand',     'status' => 'onBase'],
                ],
                'night' => [
                    ['name' => 'Mourad Belkacem', 'function' => 'Mud Engineer',   'status' => 'onsite'],
                    ['name' => 'Sofiane Rais',    'function' => 'Safety Officer', 'status' => 'onsite'],
                ],
            ],
            'RIG-002' => [
                'day'   => [
                    ['name' => 'Bilal Ouali',      'function' => 'Electrician', 'status' => 'onsite'],
                    ['name' => 'Nabil Gaci',       'function' => 'Mechanic',    'status' => 'onsite'],
                    ['name' => 'Rachid Benmoussa', 'function' => 'Roughneck',   'status' => 'onsite'],
                ],
                'night' => [],
            ],
            'RIG-003' => [
                'day'   => [
                    ['name' => 'Djamel Saidani', 'function' => 'Roughneck', 'status' => 'onLeave'],
                ],
                'night' => [],
            ],
            'RIG-005' => [
                'day'   => [
                    ['name' => 'Farid Zerrouki', 'function' => 'Floorhand', 'status' => 'onsite'],
                ],
                'night' => [],
            ],
        ];

        $rigsConfig = [
            [
                'code'        => 'RIG-001',
                'author'      => $karim ?? $admin,
                'start_depth' => 2050.00,
                'daily_avg'   => 120,
                'daily_var'   => 40,
                'fuel_avg'    => 450,
                'days'        => 40,
            ],
            [
                'code'        => 'RIG-002',
                'author'      => $omar ?? $admin,
                'start_depth' => 800.00,
                'daily_avg'   => 145,
                'daily_var'   => 50,
                'fuel_avg'    => 380,
                'days'        => 35,
            ],
            [
                'code'        => 'RIG-003',
                'author'      => $admin,
                'start_depth' => 350.00,
                'daily_avg'   => 156,
                'daily_var'   => 45,
                'fuel_avg'    => 410,
                'days'        => 30,
            ],
            [
                'code'        => 'RIG-005',
                'author'      => $admin,
                'start_depth' => 200.00,
                'daily_avg'   => 100,
                'daily_var'   => 30,
                'fuel_avg'    => 320,
                'days'        => 20,
            ],
        ];

        foreach ($rigsConfig as $config) {
            $rig = Rig::where('code', $config['code'])->first();
            if (!$rig) continue;

            $tools      = DrillingTool::where('rig_id', $rig->id)->get();
            $equipments = Equipment::where('current_rig_id', $rig->id)->get();
            $rigFuel    = $diesel
                ? RigMaterial::where('rig_id', $rig->id)
                ->where('material_type_id', $diesel->id)->first()
                : null;

            $rigEmployeeGroups = $employeeGroups[$config['code']] ?? ['day' => [], 'night' => []];

            $currentDepth = $config['start_depth'];

            for ($i = $config['days']; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i)->toDateString();

                if (DailyReport::where('rig_id', $rig->id)->whereDate('report_date', $date)->exists()) {
                    continue;
                }

                $hasNpt    = rand(1, 10) === 1;
                $nptHours  = $hasNpt ? round(rand(1, 6) + (rand(0, 9) / 10), 1) : 0;
                $incidents = $hasNpt ? rand(0, 2) : 0;
                $nptCauses = ['Equipment failure', 'Waiting on materials', 'Weather conditions', 'HSE stop work', 'Pump failure'];

                $progress = $hasNpt
                    ? max(0, $config['daily_avg'] - ($nptHours * rand(10, 25)))
                    : $config['daily_avg'] + rand(-$config['daily_var'], $config['daily_var']);
                $progress = max(0, (float) $progress);

                $depthStart   = $currentDepth;
                $depthEnd     = $currentDepth + $progress;
                $currentDepth = $depthEnd;

                $status = 'submitted';
                if ($i === 0) $status = 'draft';
                elseif ($i > 10) $status = 'approved';

                // إنشاء التقرير — بدون workers_count
                $report = DailyReport::create([
                    'rig_id'           => $rig->id,
                    'report_date'      => $date,
                    'created_by'       => $config['author']->id,
                    'depth_start'      => round($depthStart, 2),
                    'depth_end'        => round($depthEnd, 2),
                    'daily_progress'   => round($progress, 2),
                    'fuel_consumption' => $config['fuel_avg'] + rand(-50, 80),
                    'incidents'        => $incidents,
                    'npt_hours'        => $nptHours,
                    'npt_cause'        => $hasNpt ? $nptCauses[array_rand($nptCauses)] : null,
                    'notes'            => $this->generateNote($hasNpt, $progress, $config['daily_avg']),
                    'status'           => $status,
                ]);

                // إنشاء الـ shifts مع موظفيهم
                $this->seedShifts($report, $rigEmployeeGroups, $allEmployees);

                // BHA Tools
                if ($tools->isNotEmpty()) {
                    DailyReportTool::insert(
                        $tools->map(fn($t) => [
                            'report_id'        => $report->id,
                            'drilling_tool_id' => $t->id,
                            'quantity_used'    => $t->total_quantity,
                            'total_length'     => round($t->unit_length * $t->total_quantity, 2),
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ])->toArray()
                    );
                }

                // Equipments
                if ($equipments->isNotEmpty()) {
                    DailyReportEquipment::insert(
                        $equipments->map(fn($e) => [
                            'report_id'    => $report->id,
                            'equipment_id' => $e->id,
                            'status'       => rand(1, 10) > 1 ? 'Operational' : 'Maintenance',
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ])->toArray()
                    );
                }

                // Material log
                if ($rigFuel) {
                    $consumed  = $config['fuel_avg'] + rand(-50, 80);
                    $remaining = max(0, $rigFuel->quantity - $consumed);

                    MaterialLog::create([
                        'report_id'       => $report->id,
                        'rig_material_id' => $rigFuel->id,
                        'log_date'        => $date,
                        'consumed'        => $consumed,
                        'added'           => 0,
                        'remaining'       => $remaining,
                    ]);
                }
            }

            $rig->update(['current_depth' => round($currentDepth, 2)]);

            $this->command->line("    → {$config['code']}: {$config['days']} reports seeded, depth now {$currentDepth}m");
        }

        $this->command->info('  ✅ Daily Reports seeded (with shifts and employees)');
    }

    // ── ينشئ shifts التقرير ويربط الموظفين ───────────────────────────

    private function seedShifts(DailyReport $report, array $groups, $allEmployees): void
    {
        foreach (['day', 'night'] as $periode) {
            $empList = $groups[$periode] ?? [];
            if (empty($empList)) continue;

            $shift = Shift::create([
                'report_id' => $report->id,
                'periode'   => $periode,
            ]);

            $syncData = [];
            foreach ($empList as $entry) {
                $employee = $allEmployees->firstWhere('full_name', $entry['name']);
                if (!$employee) continue;

                $syncData[$employee->id] = [
                    'function' => $entry['function'],
                    'status'   => $entry['status'],
                ];
            }

            if (!empty($syncData)) {
                $shift->employees()->sync($syncData);
            }
        }
    }

    // ── ملاحظات تلقائية ───────────────────────────────────────────────

    private function generateNote(bool $hasNpt, float $progress, int $avgProgress): ?string
    {
        if ($hasNpt) {
            $notes = [
                'Minor equipment delay. Issue resolved after %d hours.',
                'Pump pressure drop detected. Maintenance team dispatched.',
                'Waiting on drill string delivery. Operations resumed.',
                'Weather delay due to sandstorm. Operations resumed.',
                'HSE safety stop — tool inspection completed successfully.',
            ];
            return sprintf($notes[array_rand($notes)], rand(1, 4));
        }

        if ($progress > $avgProgress * 1.2) {
            $notes = [
                'Excellent progress today. Favorable formation conditions.',
                'Drilling progressing smoothly. No issues reported.',
                'Optimal ROP achieved. Formation as expected.',
            ];
            return $notes[array_rand($notes)];
        }

        if ($progress > 0) {
            $notes = [
                'Normal operations. All systems running efficiently.',
                'Steady progress. Mud properties within range.',
                'Bit performance nominal. Formation slightly harder than prognosis.',
                null,
            ];
            return $notes[array_rand($notes)];
        }

        return null;
    }
}
