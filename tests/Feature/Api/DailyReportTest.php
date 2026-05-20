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
}
