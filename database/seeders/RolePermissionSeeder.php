<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'tv-display.view',

            'rigs.view',
            'rigs.create',
            'rigs.edit',
            'rigs.delete',
            'rigs.status.update',

            'locations.view',
            'locations.create',
            'locations.edit',
            'locations.delete',

            'daily-reports.view',
            'daily-reports.create',
            'daily-reports.edit',
            'daily-reports.delete',
            'daily-reports.submit',
            'daily-reports.approve',
            'daily-reports-summary.view',

            'drilling-tools.view',
            'drilling-tools.create',
            'drilling-tools.edit',
            'drilling-tools.delete',

            'tool-types.view',
            'bha.view',

            'materials.view',
            'rig-materials.manage',
            'material-logs.view',
            'material-types.view',
            'material-types.create',
            'fuel-stats.view',

            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.delete',
            'employees.status.update',

            'positions.view',
            'positions.create',

            'shifts.view',
            'shifts.create',
            'shifts.edit',
            'shifts.delete',
            'shift-employees.attach',
            'shift-employees.detach',

            'equipment.view',
            'equipment.create',
            'equipment.edit',
            'equipment.delete',
            'equipment-stats.view',

            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.active.toggle',
            'users.roles.assign',

            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $rigManagerPermissions = [
            'dashboard.view',
            'tv-display.view',

            'rigs.view',
            'rigs.edit',
            'rigs.status.update',

            'locations.view',

            'daily-reports.view',
            'daily-reports.create',
            'daily-reports.edit',
            'daily-reports.delete',
            'daily-reports.submit',
            'daily-reports-summary.view',

            'drilling-tools.view',
            'drilling-tools.create',
            'drilling-tools.edit',
            'drilling-tools.delete',

            'tool-types.view',
            'bha.view',

            'materials.view',
            'rig-materials.manage',
            'material-logs.view',
            'material-types.view',
            'fuel-stats.view',

            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.status.update',

            'positions.view',
            'positions.create',

            'shifts.view',
            'shifts.create',
            'shifts.edit',
            'shift-employees.attach',
            'shift-employees.detach',

            'equipment.view',
            'equipment.create',
            'equipment.edit',
            'equipment-stats.view',
        ];

        Role::firstOrCreate(['name' => 'Super_Admin', 'guard_name' => 'web'])
            ->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'Rig_Manager', 'guard_name' => 'web'])
            ->syncPermissions($rigManagerPermissions);

        $legacyRigManager = Role::where('name', 'Rig_manager')->first();
        if ($legacyRigManager) {
            $legacyRigManager->syncPermissions($rigManagerPermissions);
        }

        $legacyAdmin = Role::where('name', 'Admin')->first();
        if ($legacyAdmin) {
            $legacyAdmin->syncPermissions($permissions);
        }

        $legacyManager = Role::where('name', 'Manager')->first();
        if ($legacyManager) {
            $legacyManager->syncPermissions($rigManagerPermissions);
        }

        User::where('email', 'admin@gmail.com')->first()?->assignRole('Super_Admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Permissions and role assignments seeded successfully.');
    }
}
