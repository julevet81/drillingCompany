<?php

namespace Tests\Feature\Api;

use App\Models\DailyReport;
use App\Models\Rig;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportDepthUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Rig $rig;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super_Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super_Admin');
        $this->rig = Rig::factory()->create(['current_depth' => 2000]);
    }

    public function test_can_update_depth_and_fuel_fields_when_editing_daily_report(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'           => $this->rig->id,
            'created_by'       => $this->admin->id,
            'status'           => 'draft',
            'depth_start'      => 2000,
            'depth_end'        => 2100,
            'daily_progress'   => 100,
            'fuel_consumption' => 400,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'depth_start'      => 2100,
                'depth_end'        => 2250,
                'fuel_consumption' => 520,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.depth_start', '2100.00')
            ->assertJsonPath('data.depth_end', '2250.00')
            ->assertJsonPath('data.daily_progress', '150.00')
            ->assertJsonPath('data.fuel_consumption', '520.00');

        $this->assertDatabaseHas('daily_reports', [
            'id'               => $report->id,
            'depth_start'      => 2100,
            'depth_end'        => 2250,
            'daily_progress'   => 150,
            'fuel_consumption' => 520,
        ]);
    }

    public function test_can_update_depth_fields_with_full_report_payload(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'           => $this->rig->id,
            'created_by'       => $this->admin->id,
            'status'           => 'approved',
            'depth_start'      => 2000,
            'depth_end'        => 2100,
            'daily_progress'   => 100,
            'fuel_consumption' => 400,
        ]);

        $show = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/daily-reports/{$report->id}")
            ->assertStatus(200)
            ->json('data');

        $show['depth_start'] = 2100;
        $show['depth_end'] = 2250;
        $show['fuel_consumption'] = 520;

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", $show)
            ->assertStatus(200)
            ->assertJsonPath('data.depth_start', '2100.00')
            ->assertJsonPath('data.depth_end', '2250.00')
            ->assertJsonPath('data.daily_progress', '150.00')
            ->assertJsonPath('data.fuel_consumption', '520.00');

        $this->assertDatabaseHas('daily_reports', [
            'id'               => $report->id,
            'depth_start'      => 2100,
            'depth_end'        => 2250,
            'daily_progress'   => 150,
            'fuel_consumption' => 520,
        ]);
    }

    public function test_camel_case_depth_fields_are_not_updated_without_mapping(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'           => $this->rig->id,
            'created_by'       => $this->admin->id,
            'depth_start'      => 2000,
            'depth_end'        => 2100,
            'daily_progress'   => 100,
            'fuel_consumption' => 400,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'depthStart'      => 2100,
                'depthEnd'        => 2250,
                'dailyProgress'   => 150,
                'fuelConsumption' => 520,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('daily_reports', [
            'id'               => $report->id,
            'depth_start'      => 2000,
            'depth_end'        => 2100,
            'daily_progress'   => 100,
            'fuel_consumption' => 400,
        ]);
    }

    public function test_sending_daily_progress_in_payload_does_not_block_depth_update(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'           => $this->rig->id,
            'created_by'       => $this->admin->id,
            'depth_start'      => 2000,
            'depth_end'        => 2100,
            'daily_progress'   => 100,
            'fuel_consumption' => 400,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'depth_start'      => 2100,
                'depth_end'        => 2250,
                'daily_progress'   => 999, // stale client value — should be recalculated
                'fuel_consumption' => 520,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.daily_progress', '150.00');

        $this->assertDatabaseHas('daily_reports', [
            'id'             => $report->id,
            'daily_progress' => 150,
        ]);
    }

    public function test_can_update_depth_fields_alongside_shifts(): void
    {
        $report = DailyReport::factory()->create([
            'rig_id'           => $this->rig->id,
            'created_by'       => $this->admin->id,
            'status'           => 'draft',
            'depth_start'      => 2000,
            'depth_end'        => 2100,
            'daily_progress'   => 100,
            'fuel_consumption' => 400,
        ]);

        \App\Models\Shift::create([
            'report_id'  => $report->id,
            'post'       => 'post_1',
            'start_time' => '08:00',
            'end_time'   => '20:00',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/daily-reports/{$report->id}", [
                'depth_start'      => 2100,
                'depth_end'        => 2250,
                'fuel_consumption' => 520,
                'shifts'           => [
                    [
                        'post'        => 'post_1',
                        'start_time'  => '08:00',
                        'end_time'    => '20:00',
                        'description' => 'Updated shift',
                    ],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('daily_reports', [
            'id'               => $report->id,
            'depth_start'      => 2100,
            'depth_end'        => 2250,
            'daily_progress'   => 150,
            'fuel_consumption' => 520,
        ]);
    }
}
