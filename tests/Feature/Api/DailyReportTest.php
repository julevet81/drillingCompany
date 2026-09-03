<?php

namespace Tests\Feature\Api;

use App\Models\DailyReport;
use App\Models\Rig;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Rig  $rig;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super_Admin']);
        Role::create(['name' => 'well_manager']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super_Admin');
        $this->rig = Rig::factory()->create(['current_depth' => 2000]);
    }

    public function test_can_create_daily_report_with_npt(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'           => $this->rig->id,
                'report_date'      => today()->toDateString(),
                'depth_start'      => 2000,
                'depth_end'        => 2150,
                'workers_count'    => 25,
                'fuel_consumption' => 450,
                'incidents'        => 1,
                'npt_hours'        => 2.5,
                'npt_cause'        => 'Equipment failure',
                'notes'            => 'Test report',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.incidents', 1)
            ->assertJsonPath('data.npt_hours', '2.50');

        // Rig current depth should be updated
        $this->assertDatabaseHas('rigs', ['id' => $this->rig->id, 'current_depth' => 2150]);
    }

    public function test_daily_progress_is_auto_calculated(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'      => $this->rig->id,
                'report_date' => today()->toDateString(),
                'depth_start' => 2000,
                'depth_end'   => 2180,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.daily_progress', '180.00');
    }

    public function test_updating_the_latest_report_depth_updates_the_rig_depth(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id' => $this->rig->id,
            'report_date' => today(),
            'depth_start' => 2000,
            'depth_end' => 2150,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'depth_start' => 2150,
                'depth_end' => 2230,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('rigs', [
            'id' => $this->rig->id,
            'current_depth' => 2230,
        ]);
    }

    public function test_can_update_rig_drilling_phase_when_creating_daily_report(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'         => $this->rig->id,
                'report_date'    => today()->toDateString(),
                'depth_start'    => 2000,
                'depth_end'      => 2180,
                'drilling_phase' => 'Drilling 8 1/2',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.rig.drilling_phase', 'Drilling 8 1/2');

        $this->assertDatabaseHas('rigs', [
            'id'             => $this->rig->id,
            'drilling_phase' => 'Drilling 8 1/2',
        ]);
    }

    public function test_creating_daily_report_updates_rig_drilling_phase_from_payload(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'           => $this->rig->id,
                'report_date'      => today()->toDateString(),
                'depth_start'      => 1320,
                'depth_end'        => 1445,
                'fuel_consumption' => 480,
                'incidents'        => 1,
                'npt_hours'        => 2.5,
                'npt_cause'        => 'Pump failure',
                'notes'            => 'Drilling progressing smoothly.',
                'rig_status'       => 'drilling',
                'rig_notes'        => 'there is an incident',
                'drilling_phase'   => '845',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.rig.drilling_phase', '845')
            ->assertJsonPath('data.drilling_phase', '845');

        $this->assertDatabaseHas('rigs', [
            'id'             => $this->rig->id,
            'drilling_phase' => '845',
            'notes'          => 'there is an incident',
        ]);
    }

    public function test_can_update_rig_drilling_phase_alias_when_creating_daily_report(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'              => $this->rig->id,
                'report_date'         => today()->toDateString(),
                'depth_start'         => 2000,
                'depth_end'           => 2180,
                'rig_drilling_phase'  => 'Casing 9 5/8',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.rig.drilling_phase', 'Casing 9 5/8')
            ->assertJsonPath('data.drilling_phase', 'Casing 9 5/8');

        $this->assertDatabaseHas('rigs', [
            'id'             => $this->rig->id,
            'drilling_phase' => 'Casing 9 5/8',
        ]);
    }

    public function test_can_update_rig_drilling_phase_nested_when_creating_daily_report(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'         => $this->rig->id,
                'report_date'    => today()->toDateString(),
                'depth_start'    => 2000,
                'depth_end'      => 2180,
                'rig'            => [
                    'drilling_phase' => 'Nested Phase Test',
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.rig.drilling_phase', 'Nested Phase Test')
            ->assertJsonPath('data.drilling_phase', 'Nested Phase Test');

        $this->assertDatabaseHas('rigs', [
            'id'             => $this->rig->id,
            'drilling_phase' => 'Nested Phase Test',
        ]);
    }


    public function test_can_update_rig_drilling_phase_when_editing_daily_report(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'drilling_phase' => 'Casing 13 3/8',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.rig.drilling_phase', 'Casing 13 3/8');

        $this->assertDatabaseHas('rigs', [
            'id'             => $this->rig->id,
            'drilling_phase' => 'Casing 13 3/8',
        ]);
    }

    public function test_duplicate_report_for_same_rig_date_is_rejected(): void
    {
        DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'report_date' => today()->toDateString(),
            'created_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'      => $this->rig->id,
                'report_date' => today()->toDateString(),
                'depth_start' => 2000,
                'depth_end'   => 2100,
            ])
            ->assertStatus(422);
    }

    public function test_can_submit_draft_report(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/daily-reports/{$report->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_admin_can_approve_submitted_report(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'     => $this->rig->id,
            'created_by' => $this->admin->id,
            'status'     => 'submitted',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/daily-reports/{$report->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_cannot_edit_approved_report(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'     => $this->rig->id,
            'created_by' => $this->admin->id,
            'status'     => 'approved',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", ['notes' => 'Try to edit'])
            ->assertStatus(422);
    }

    public function test_depth_end_must_be_gte_depth_start(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id'      => $this->rig->id,
                'report_date' => today()->toDateString(),
                'depth_start' => 2000,
                'depth_end'   => 1800,  // LESS than start — invalid
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['depth_end']);
    }

    public function test_can_get_daily_reports_summary(): void
    {
        $rigs = Rig::factory()->count(3)->create();
        foreach ($rigs as $rig) {
            DailyReport::factory()->create([
                'rig_id'      => $rig->id,
                'report_date' => today()->toDateString(),
                'created_by'  => $this->admin->id,
            ]);
        }

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/daily-reports/summary?date=' . today()->toDateString())
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'total_reports', 'avg_progress_m', 'total_personnel',
                'avg_bha_length_m', 'total_materials',
            ]]);
    }

    public function test_daily_report_endpoints_return_rig_drilling_phase(): void
    {
        $rig = Rig::factory()->create(['drilling_phase' => 'Phase 123']);
        $report = DailyReport::factory()->create([
            'rig_id'      => $rig->id,
            'report_date' => today()->toDateString(),
            'created_by'  => $this->admin->id,
        ]);

        // 1. Index (عرض الكل)
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/daily-reports')
            ->assertStatus(200)
            ->assertJsonPath('data.0.rig.drilling_phase', 'Phase 123')
            ->assertJsonPath('data.0.drilling_phase', 'Phase 123');

        // 2. Show (واحد)
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/daily-reports/{$report->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.rig.drilling_phase', 'Phase 123')
            ->assertJsonPath('data.drilling_phase', 'Phase 123');

        // 3. Last (الاخير)
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/daily-reports/last/{$rig->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.rig.drilling_phase', 'Phase 123')
            ->assertJsonPath('data.drilling_phase', 'Phase 123');

        // 4. By Rig (عرض الكل لريغ معين)
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/daily-reports/by-rig/{$rig->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.0.rig.drilling_phase', 'Phase 123')
            ->assertJsonPath('data.0.drilling_phase', 'Phase 123');
    }

    public function test_can_add_employee_to_shift_when_updating_daily_report(): void
    {
        $position = \App\Models\Position::create(['name' => 'Driller']);
        $employee = \App\Models\Employee::create([
            'full_name' => 'John Doe',
            'position_id' => $position->id,
            'rig_id' => $this->rig->id,
        ]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'post' => 'post_1',
                        'start_time' => '08:00',
                        'end_time' => '20:00',
                        'employees' => [
                            [
                                'employee_id' => $employee->id,
                                'function' => 'Driller',
                                'status' => 'onsite',
                            ]
                        ]
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employee->id,
            'function' => 'Driller',
            'status' => 'onsite',
        ]);
    }

    public function test_can_update_employees_via_flat_array_when_updating_daily_report(): void
    {
        $position = \App\Models\Position::create(['name' => 'Driller']);
        $employee = \App\Models\Employee::create([
            'full_name' => 'John Doe',
            'position_id' => $position->id,
            'rig_id' => $this->rig->id,
        ]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'employees' => [
                    [
                        'id' => $employee->id,
                        'shift' => 'post_1',
                        'function' => 'Driller',
                        'status' => 'onsite',
                    ]
                ]
            ]);

        $response->assertStatus(200);

        $shift = $report->shifts()->where('post', 'post_1')->first();
        $this->assertNotNull($shift);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employee->id,
            'function' => 'Driller',
            'status' => 'onsite',
        ]);
    }

    public function test_can_replace_shift_employees_when_updating_daily_report_by_shift_id(): void
    {
        $position = \App\Models\Position::create(['name' => 'Driller']);
        $oldEmployee = \App\Models\Employee::create([
            'full_name' => 'Old Employee',
            'position_id' => $position->id,
            'rig_id' => $this->rig->id,
        ]);
        $newEmployee = \App\Models\Employee::create([
            'full_name' => 'New Employee',
            'position_id' => $position->id,
            'rig_id' => $this->rig->id,
        ]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $shift->employees()->sync([
            $oldEmployee->id => [
                'function' => 'Old Function',
                'status' => 'onsite',
            ],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'id' => $shift->id,
                        'employees' => [
                            [
                                'id' => $newEmployee->id,
                                'function' => 'New Function',
                                'status' => 'onBase',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.shifts.0.employees.0.id', $newEmployee->id);

        $this->assertDatabaseMissing('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $oldEmployee->id,
        ]);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $newEmployee->id,
            'function' => 'New Function',
            'status' => 'onBase',
        ]);
    }

    public function test_show_daily_report_returns_all_shift_employees_after_update(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $employees = collect(['One', 'Two', 'Three'])->map(fn($name) => \App\Models\Employee::create([
            'full_name' => "Employee {$name}",
            'position_id' => $position->id,
            'rig_id' => $this->rig->id,
        ]));

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'id' => $shift->id,
                        'employees' => $employees->map(fn($employee) => [
                            'id' => $employee->id,
                            'function' => 'Crew',
                            'status' => 'onsite',
                        ])->values()->all(),
                    ],
                ],
            ])
            ->assertStatus(200);

        $this->assertSame(3, \DB::table('employee_shifts')->where('shift_id', $shift->id)->count());

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/daily-reports/{$report->id}")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.shifts.0.employees')
            ->assertJsonCount(3, 'data.employees');
    }

    public function test_update_daily_report_replaces_original_shift_employees_in_response_and_database(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $employeeA = \App\Models\Employee::create(['full_name' => 'Employee A', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);
        $employeeB = \App\Models\Employee::create(['full_name' => 'Employee B', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);
        $employeeC = \App\Models\Employee::create(['full_name' => 'Employee C', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $shift->employees()->sync([
            $employeeA->id => ['function' => 'Old A', 'status' => 'onsite'],
            $employeeB->id => ['function' => 'Old B', 'status' => 'onsite'],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'post' => 'post_1',
                        'employees' => [
                            ['employee_id' => $employeeB->id, 'function' => 'Updated B', 'status' => 'onBase'],
                            ['employee_id' => $employeeC->id, 'function' => 'New C', 'status' => 'onsite'],
                        ],
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.shifts.0.employees')
            ->assertJsonCount(2, 'data.employees')
            ->assertJsonPath('data.employees.0.id', $employeeB->id)
            ->assertJsonPath('data.employees.1.id', $employeeC->id);

        $this->assertDatabaseMissing('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employeeA->id,
        ]);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employeeB->id,
            'function' => 'Updated B',
            'status' => 'onBase',
        ]);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employeeC->id,
            'function' => 'New C',
            'status' => 'onsite',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/daily-reports/{$report->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.shifts.0.employees')
            ->assertJsonCount(2, 'data.employees');
    }

    public function test_can_update_flat_employees_by_shift_id_when_updating_daily_report(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $oldEmployee = \App\Models\Employee::create(['full_name' => 'Old Employee', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);
        $newEmployee = \App\Models\Employee::create(['full_name' => 'New Employee', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $shift->employees()->sync([$oldEmployee->id => ['function' => 'Old', 'status' => 'onsite']]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'employees' => [
                    [
                        'shift_id' => $shift->id,
                        'id' => $newEmployee->id,
                        'function' => 'New',
                        'status' => 'onBase',
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.id', $newEmployee->id);

        $this->assertDatabaseMissing('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $oldEmployee->id,
        ]);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $newEmployee->id,
            'function' => 'New',
            'status' => 'onBase',
        ]);
    }

    public function test_can_clear_all_report_employees_with_empty_flat_employees_array(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $employee = \App\Models\Employee::create(['full_name' => 'Employee', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $shift->employees()->sync([$employee->id => ['function' => 'Crew', 'status' => 'onsite']]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", ['employees' => []])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.employees')
            ->assertJsonCount(0, 'data.shifts.0.employees');

        $this->assertDatabaseMissing('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_update_daily_report_keeps_all_employees_across_two_shifts(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $employees = collect(['A', 'B', 'C'])->map(fn($name) => \App\Models\Employee::create([
            'full_name' => "Employee {$name}",
            'position_id' => $position->id,
            'rig_id' => $this->rig->id,
        ]))->values();

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ])->employees()->sync([$employees[0]->id => ['function' => 'Old', 'status' => 'onsite']]);

        \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_2',
            'start_time' => '20:00',
            'end_time' => '08:00',
        ])->employees()->sync([$employees[0]->id => ['function' => 'Old', 'status' => 'onsite']]);

        $payloadEmployees = $employees->map(fn($employee) => [
            'employee_id' => $employee->id,
            'function' => 'Crew',
            'status' => 'onsite',
        ])->all();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'post' => 'post_1',
                        'employees' => $payloadEmployees,
                    ],
                    [
                        'post' => 'post_2',
                        'employees' => $payloadEmployees,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.shifts.0.employees')
            ->assertJsonCount(3, 'data.shifts.1.employees')
            ->assertJsonCount(6, 'data.employees');

        $this->assertSame(6, \DB::table('employee_shifts')
            ->whereIn('shift_id', $report->shifts()->pluck('id'))
            ->count());
    }

    public function test_update_daily_report_prefers_shift_employees_over_stale_flat_employees(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $oldEmployee = \App\Models\Employee::create(['full_name' => 'Old Employee', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);
        $newEmployee = \App\Models\Employee::create(['full_name' => 'New Employee', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $shift->employees()->sync([$oldEmployee->id => ['function' => 'Old', 'status' => 'onsite']]);

        $staleFlatEmployees = [
            [
                'id' => $oldEmployee->id,
                'shift_id' => $shift->id,
                'shift' => 'post_1',
                'function' => 'Old',
                'status' => 'onsite',
            ],
        ];

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'id' => $shift->id,
                        'post' => 'post_1',
                        'employees' => [
                            [
                                'id' => $newEmployee->id,
                                'function' => 'New',
                                'status' => 'onBase',
                            ],
                        ],
                    ],
                ],
                'employees' => $staleFlatEmployees,
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.shifts.0.employees')
            ->assertJsonPath('data.shifts.0.employees.0.id', $newEmployee->id)
            ->assertJsonPath('data.employees.0.id', $newEmployee->id);

        $this->assertDatabaseMissing('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $oldEmployee->id,
        ]);
        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $newEmployee->id,
            'function' => 'New',
            'status' => 'onBase',
        ]);
    }

    public function test_update_daily_report_removes_employee_from_shift_when_payload_has_one_employee(): void
    {
        $position = \App\Models\Position::create(['name' => 'Crew']);
        $employeeA = \App\Models\Employee::create(['full_name' => 'Employee A', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);
        $employeeB = \App\Models\Employee::create(['full_name' => 'Employee B', 'position_id' => $position->id, 'rig_id' => $this->rig->id]);

        $report = DailyReport::factory()->create([
            'rig_id'      => $this->rig->id,
            'created_by'  => $this->admin->id,
            'status'      => 'draft',
            'report_date' => today()->toDateString(),
        ]);

        $shift = \App\Models\Shift::create([
            'report_id' => $report->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $shift->employees()->sync([
            $employeeA->id => ['function' => 'A', 'status' => 'onsite'],
            $employeeB->id => ['function' => 'B', 'status' => 'onsite'],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'shifts' => [
                    [
                        'shift_id' => $shift->id,
                        'employees' => [
                            ['employee_id' => $employeeA->id, 'function' => 'A', 'status' => 'onsite'],
                        ],
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.shifts.0.employees')
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.id', $employeeA->id);

        $this->assertDatabaseHas('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employeeA->id,
        ]);
        $this->assertDatabaseMissing('employee_shifts', [
            'shift_id' => $shift->id,
            'employee_id' => $employeeB->id,
        ]);
        $this->assertSame(1, \DB::table('employee_shifts')->where('shift_id', $shift->id)->count());
    }

    public function test_equipment_hours_of_operation_records_last_number_not_sum(): void
    {
        $equipment = \App\Models\Equipment::factory()->create([
            'current_rig_id' => null,
            'hours_of_operation' => 10,
        ]);

        // 1. Create a daily report with hours_used = 15
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/daily-reports', [
                'rig_id' => $this->rig->id,
                'report_date' => now()->toDateString(),
                'depth_start' => 2000,
                'depth_end' => 2120,
                'equipments' => [
                    [
                        'equipment_id' => $equipment->id,
                        'hours_used' => 15,
                        'status' => 'Operational',
                    ]
                ],
            ])
            ->assertStatus(201);

        $reportId = $response->json('data.id');

        // The equipment hours should be 15, not 10 + 15 = 25
        $this->assertEquals(15.00, $equipment->fresh()->hours_of_operation);
        $this->assertSame($this->rig->id, $equipment->fresh()->current_rig_id);

        // Simulate legacy/unassigned data before editing the report. Updating
        // the report must assign its equipment to the report's rig as well.
        $equipment->update(['current_rig_id' => null]);

        // 2. Update the daily report with hours_used = 22
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$reportId}", [
                'depth_start' => 2000,
                'depth_end' => 2120,
                'equipments' => [
                    [
                        'equipment_id' => $equipment->id,
                        'hours_used' => 22,
                        'status' => 'Operational',
                    ]
                ],
            ])
            ->assertStatus(200);

        // The equipment hours should be 22, not 15 + 22 = 37 or other summed values
        $this->assertEquals(22.00, $equipment->fresh()->hours_of_operation);
        $this->assertSame($this->rig->id, $equipment->fresh()->current_rig_id);
    }
}
