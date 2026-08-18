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
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'well_manager']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
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
}
