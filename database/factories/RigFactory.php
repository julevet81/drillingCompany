<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Rig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rig>
 */
class RigFactory extends Factory
{
    protected $model = Rig::class;

    public function definition(): array
    {
        return [
            'name' => 'Rig ' . fake()->unique()->bothify('??-###'),
            'code' => fake()->unique()->bothify('RIG-###'),
            'location_id' => Location::factory(),
            'status' => 'active',
            'current_depth' => fake()->numberBetween(1000, 3000),
            'target_depth' => fake()->numberBetween(3500, 6000),
            'drilling_phase' => 'Drilling 8½"',
            'start_date' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
