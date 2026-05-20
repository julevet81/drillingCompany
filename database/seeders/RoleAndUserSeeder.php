<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────────────────────
        $roles = ['super_admin', 'well_manager', 'operator', 'viewer'];
        foreach ($roles as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $admin   = Role::where('name', 'Super_Admin')->first();
        $manager = Role::where('name', 'Rig_manager')->first();

        // ── Users (matching UI screenshots) ──────────────────────────
        $users = [
            [
                'full_name' => 'Ahmed Benali',
                'email'     => 'ahmed.benali@oms.dz',
                'phone'     => '+213 555 123 456',
                'password'  => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'full_name' => 'Karim Messaoudi',
                'email'     => 'karim.messaoudi@oms.dz',
                'phone'     => '+213 555 234 567',
                'password'  => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'full_name' => 'Omar Saidi',
                'email'     => 'omar.saidi@oms.dz',
                'phone'     => '+213 555 345 678',
                'password'  => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'full_name' => 'Yacine Boudiaf',
                'email'     => 'yacine.boudiaf@oms.dz',
                'phone'     => '+213 555 456 789',
                'password'  => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'full_name' => 'Farid Zerrouki',
                'email'     => 'farid.zerrouki@oms.dz',
                'phone'     => '+213 555 567 890',
                'password'  => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'full_name' => 'Tarek Hamdi',
                'email'     => 'tarek.hamdi@oms.dz',
                'phone'     => '+213 555 678 901',
                'password'  => Hash::make('password'),
                'is_active' => false,
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(['email' => $data['email']], $data);
        }

        $this->command->info('  ✅ Roles & Users seeded (6 users)');
    }
}
