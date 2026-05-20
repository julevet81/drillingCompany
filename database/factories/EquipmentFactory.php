<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\Rig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'current_rig_id' => Rig::factory(),
            'name' => fake()->randomElement(['Mud Pump', 'Top Drive', 'Generator', 'Compressor']),
            'marque' => fake()->company(),
            'serial_number' => fake()->unique()->bothify('EQ-#####'),
        ];
    }
}
