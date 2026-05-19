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

        $this->admin   = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->value('id')]);
        $this->manager = User::factory()->create(['role_id' => Role::where('name', 'well_manager')->value('id')]);
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
}
