<?php

namespace Database\Factories;

use App\Models\DailyReport;
use App\Models\Rig;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReport>
 */
class DailyReportFactory extends Factory
{
    protected $model = DailyReport::class;

    public function definition(): array
    {
        $depthStart = fake()->numberBetween(1500, 2500);
        $depthEnd = $depthStart + fake()->numberBetween(20, 200);

        return [
            'rig_id' => Rig::factory(),
            'report_date' => now()->toDateString(),
            'created_by' => User::factory(),
            'depth_start' => $depthStart,
            'depth_end' => $depthEnd,
            'daily_progress' => $depthEnd - $depthStart,
            'workers_count' => fake()->numberBetween(10, 40),
            'fuel_consumption' => fake()->numberBetween(100, 600),
            'incidents' => fake()->numberBetween(0, 2),
            'npt_hours' => fake()->randomFloat(2, 0, 8),
            'npt_cause' => null,
            'notes' => fake()->optional()->sentence(),
            'status' => 'draft',
        ];
    }
}
