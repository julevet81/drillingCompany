<?php

namespace Tests\Feature\Api;

use App\Models\Equipment;
use App\Models\DailyReport;
use App\Models\DailyReportEquipment;
use App\Models\Rig;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Rig  $rig;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->rig = Rig::factory()->create();
    }

    public function test_can_create_equipment(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/equipments', [
                'current_rig_id' => $this->rig->id,
                'name'           => 'Drilling Rig',
                'marque'         => 'GEFCO T40',
                'serial_number'  => '1635 874 39',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Drilling Rig')
            ->assertJsonPath('data.marque', 'GEFCO T40');
    }

    public function test_can_list_equipment_for_rig(): void
    {
        Equipment::factory()->count(3)->create(['current_rig_id' => $this->rig->id]);
        Equipment::factory()->create(['current_rig_id' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/equipments?rig_id={$this->rig->id}")
            ->assertStatus(200);

        // Only rig's equipment
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/equipments?rig_id={$this->rig->id}");
        $this->assertCount(3, $response->json('data'));
    }

    public function test_lists_legacy_report_equipment_when_filtering_by_rig(): void
    {
        $report = DailyReport::factory()->create(['rig_id' => $this->rig->id]);
        $equipment = Equipment::factory()->create(['current_rig_id' => null]);

        DailyReportEquipment::create([
            'report_id' => $report->id,
            'equipment_id' => $equipment->id,
            'hours_used' => 12,
            'status' => 'Operational',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/equipments?rig_id={$this->rig->id}")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($equipment->id, $response->json('data.0.id'));
    }

    public function test_can_unassign_equipment_from_rig(): void
    {
        $equipment = Equipment::factory()->create(['current_rig_id' => $this->rig->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/equipments/{$equipment->id}", ['current_rig_id' => null])
            ->assertStatus(200);

        $this->assertDatabaseHas('equipments', [
            'id'             => $equipment->id,
            'current_rig_id' => null,
        ]);
    }

    public function test_can_get_equipment_stats(): void
    {
        Equipment::factory()->count(4)->create(['current_rig_id' => $this->rig->id]);
        Equipment::factory()->count(2)->create(['current_rig_id' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/equipments/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.deployed', 4)
            ->assertJsonPath('data.unassigned', 2);
    }
}
