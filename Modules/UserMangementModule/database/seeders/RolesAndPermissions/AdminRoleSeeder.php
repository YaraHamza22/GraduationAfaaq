<?php

namespace Modules\UserMangementModule\Database\Seeders\RolesAndPermissions;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\UserMangementModule\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('admin', 'api');
        $permissions = Permission::where('guard_name', 'api')->get();
        $adminRole->syncPermissions($permissions);

        $admins = [
            'admin@example.com',
        ];

        foreach ($admins as $adminEmail) {
            $admin = User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Admin',
                    'email' => $adminEmail,
                    'password' => Hash::make('Admin@123456'),
                    'phone' => '+963991554554',
                    'date_of_birth' => '2000-01-01',
                    'gender' => 'male',
                ]
            );

            $admin->syncRoles([$adminRole->name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
