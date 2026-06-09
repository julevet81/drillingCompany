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
            'view dashboard',
            'view tv display',

            'view rigs',
            'create rigs',
            'edit rigs',
            'delete rigs',
            'update rig status',

            'view locations',
            'create locations',
            'edit locations',
            'delete locations',

            'view daily reports',
            'create daily reports',
            'edit daily reports',
            'delete daily reports',
            'submit daily reports',
            'approve daily reports',
            'view daily reports summary',

            'view drilling tools',
            'create drilling tools',
            'edit drilling tools',
            'delete drilling tools',
            'view tool types',
            'view bha',

            'view materials',
            'manage rig materials',
            'view material logs',
            'view material types',
            'create material types',
            'view fuel stats',

            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'update employee status',
            'view positions',
            'create positions',

            'view shifts',
            'create shifts',
            'edit shifts',
            'delete shifts',
            'attach shift employees',
            'detach shift employees',

            'view equipment',
            'create equipment',
            'edit equipment',
            'delete equipment',
            'view equipment stats',

            'view users',
            'create users',
            'edit users',
            'delete users',
            'toggle users active',
            'assign user roles',

            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $rigManagerPermissions = [
            'view dashboard',
            'view tv display',

            'view rigs',
            'edit rigs',
            'update rig status',

            'view locations',

            'view daily reports',
            'create daily reports',
            'edit daily reports',
            'delete daily reports',
            'submit daily reports',
            'view daily reports summary',

            'view drilling tools',
            'create drilling tools',
            'edit drilling tools',
            'delete drilling tools',
            'view tool types',
            'view bha',

            'view materials',
            'manage rig materials',
            'view material logs',
            'view material types',
            'view fuel stats',

            'view employees',
            'create employees',
            'edit employees',
            'update employee status',
            'view positions',
            'create positions',

            'view shifts',
            'create shifts',
            'edit shifts',
            'attach shift employees',
            'detach shift employees',

            'view equipment',
            'create equipment',
            'edit equipment',
            'view equipment stats',
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
