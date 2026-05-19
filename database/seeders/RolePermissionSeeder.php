<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('Permissions seeded successfully.');

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web'])->syncPermissions($permissions);
        $user = User::findOrFail(1); // Assuming the first user is the admin
        $user->assignRole('Admin');
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web'])->syncPermissions(['view users']);
        $this->command->info('Roles and permissions seeded successfully.');
    }
}