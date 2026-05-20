<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'full_name' => 'Admin super',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('12345678'),
        ]);

        $this->call([
            MasterDataSeeder::class,
            RoleAndUserSeeder::class,
            LocationAndRigSeeder::class,
            EmployeeAndShiftSeeder::class,
            EquipmentSeeder::class,
            DrillingToolSeeder::class,
            MaterialStockSeeder::class,
            DailyReportSeeder::class,

        ]);

        $admin = User::where('email', 'admin@gmail.com')->first();
        $admin->assignRole('Super_Admin');
    }
}
