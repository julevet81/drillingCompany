<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\Rig;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'well_manager']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('well_manager');
    }

    public function test_can_list_rigs(): void
    {
        Rig::factory()->count(5)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/rigs')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    public function test_can_filter_rigs_by_status(): void
    {
        Rig::factory()->count(3)->active()->create();
        Rig::factory()->count(2)->create(['status' => 'paused']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/rigs?status=active')
            ->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_create_rig(): void
    {
        $location = Location::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rigs', [
                'name'           => 'HMD-North-01',
                'code'           => 'RIG-001',
                'location_id'    => $location->id,
                'target_depth'   => 4200,
                'drilling_phase' => 'Drilling 8½"',
                'start_date'     => '2026-01-01',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'HMD-North-01')
            ->assertJsonPath('data.drilling_phase', 'Drilling 8½"');
    }

    public function test_manager_cannot_create_rig(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/rigs', ['name' => 'Test Rig'])
            ->assertStatus(403);
    }

    public function test_can_get_rig_detail(): void
    {
        $rig = Rig::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/rigs/{$rig->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'rig' => ['id', 'name', 'code', 'status', 'drilling_phase', 'progress_percentage'],
                    'equipments',
                    'materials',
                    'crew',
                    'recent_reports',
                    'depth_timeline',
                ],
            ]);
    }

    public function test_can_update_rig_status(): void
    {
        $rig = Rig::factory()->active()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/rigs/{$rig->id}/status", ['status' => 'paused'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paused');

        $this->assertDatabaseHas('rigs', ['id' => $rig->id, 'status' => 'paused']);
    }

    public function test_can_soft_delete_rig(): void
    {
        $rig = Rig::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/rigs/{$rig->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('rigs', ['id' => $rig->id]);
    }

    public function test_get_rig_stats(): void
    {
        Rig::factory()->count(3)->active()->create();
        Rig::factory()->count(2)->create(['status' => 'fishing']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/rigs/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['total', 'drilling', 'fishing', 'stopped']]);
    }

    public function test_rig_detail_returns_data_from_latest_report(): void
    {
        $rig = Rig::factory()->create();
        
        // Create an equipment
        $equipment = \App\Models\Equipment::factory()->create([
            'current_rig_id' => $rig->id,
            'hours_of_operation' => 10,
        ]);

        // Create a report from 2 days ago
        $report1 = \App\Models\DailyReport::factory()->create([
            'rig_id' => $rig->id,
            'report_date' => now()->subDays(2)->toDateString(),
        ]);
        
        // Create a report from 1 day ago (the latest report)
        $report2 = \App\Models\DailyReport::factory()->create([
            'rig_id' => $rig->id,
            'report_date' => now()->subDays(1)->toDateString(),
        ]);

        // Add equipment to report2 (hours_used = 15)
        \App\Models\DailyReportEquipment::create([
            'report_id' => $report2->id,
            'equipment_id' => $equipment->id,
            'hours_used' => 15,
            'status' => 'Maintenance',
        ]);

        // Add employee to shift in report2
        $shift = \App\Models\Shift::create([
            'report_id' => $report2->id,
            'post' => 'post_1',
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        $position = \App\Models\Position::create(['name' => 'Driller']);
        $employee = \App\Models\Employee::create([
            'full_name' => 'John Doe',
            'position_id' => $position->id,
            'rig_id' => $rig->id,
        ]);
        $shift->employees()->attach($employee->id, ['function' => 'Driller', 'status' => 'onsite']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/rigs/{$rig->id}")
            ->assertStatus(200);

        // Verify crew has employee
        $crew = $response->json('data.crew');
        $this->assertCount(1, $crew);
        $this->assertEquals($employee->id, $crew[0]['id']);

        // Verify equipment is from report2 with hours_used = 15 and status = Maintenance
        $equipments = $response->json('data.equipments');
        $this->assertCount(1, $equipments);
        $this->assertEquals($equipment->id, $equipments[0]['id']);
        $this->assertEquals(15.00, $equipments[0]['hours_of_operation']);
        $this->assertEquals('Maintenance', $equipments[0]['status']);
    }
}
