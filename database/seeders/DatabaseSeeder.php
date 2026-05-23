<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\AuditorRoleSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\InstructorRoleSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\PermissionSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\StudentRoleSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\SuperAdminRoleSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            StudentRoleSeeder::class,
            InstructorRoleSeeder::class,
            AuditorRoleSeeder::class,
            SuperAdminRoleSeeder::class,
        ]);
    }
}
