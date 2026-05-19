<?php

namespace Tests\Feature\Api;

use App\Models\MaterialType;
use App\Models\Rig;
use App\Models\RigMaterial;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Rig  $rig;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin']);

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->value('id'),
        ]);
        $this->rig = Rig::factory()->create();

        MaterialType::create(['name' => 'Diesel Fuel', 'unit' => 'L']);
        MaterialType::create(['name' => 'Bentonite',   'unit' => 'kg']);
    }

    public function test_can_list_material_types(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/materials/types')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_set_material_for_rig(): void
    {
        $type = MaterialType::where('name', 'Diesel Fuel')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/materials/rig/{$this->rig->id}", [
                'material_type_id' => $type->id,
                'quantity'         => 12500,
                'capacity'         => 20000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', '12500.00');
    }

    public function test_can_get_rig_materials(): void
    {
        $type = MaterialType::where('name', 'Diesel Fuel')->first();

        RigMaterial::create([
            'rig_id'           => $this->rig->id,
            'material_type_id' => $type->id,
            'quantity'         => 8000,
            'capacity'         => 20000,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/materials/rig/{$this->rig->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Diesel Fuel');
    }

    public function test_can_get_fuel_stats(): void
    {
        $type = MaterialType::where('name', 'Diesel Fuel')->first();

        RigMaterial::create([
            'rig_id'           => $this->rig->id,
            'material_type_id' => $type->id,
            'quantity'         => 12500,
            'capacity'         => 20000,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/materials/fuel-stats')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'total_capacity_l', 'current_stock_l',
                'daily_consumption_l', 'avg_days_remaining',
            ]]);
    }
}
