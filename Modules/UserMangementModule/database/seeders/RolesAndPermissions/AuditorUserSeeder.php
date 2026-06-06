<?php

namespace Modules\UserMangementModule\Database\Seeders\RolesAndPermissions;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\UserMangementModule\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuditorUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $auditorRole = Role::findOrCreate('auditor', 'api');

        $auditors = [
            [
                'name' => 'Amina Khaled',
                'email' => 'amina.auditor@example.com',
                'phone' => '+963991110001',
                'date_of_birth' => '1992-04-12',
                'gender' => 'female',
                'specialization' => 'Curriculum Quality Assurance',
                'bio' => 'Audits course structure, assessment alignment, and learner-facing content quality.',
                'years_of_experience' => 6,
            ],
            [
                'name' => 'Omar Suleiman',
                'email' => 'omar.auditor@example.com',
                'phone' => '+963991110002',
                'date_of_birth' => '1989-09-08',
                'gender' => 'male',
                'specialization' => 'Assessment Review',
                'bio' => 'Reviews quizzes, grading fairness, and assessment coverage across courses.',
                'years_of_experience' => 8,
            ],
            [
                'name' => 'Lina Haddad',
                'email' => 'lina.auditor@example.com',
                'phone' => '+963991110003',
                'date_of_birth' => '1994-01-23',
                'gender' => 'female',
                'specialization' => 'Instructional Design',
                'bio' => 'Evaluates lesson flow, accessibility, and clarity of instructional materials.',
                'years_of_experience' => 5,
            ],
            [
                'name' => 'Tariq Nasser',
                'email' => 'tariq.auditor@example.com',
                'phone' => '+963991110004',
                'date_of_birth' => '1990-11-15',
                'gender' => 'male',
                'specialization' => 'Compliance and Content Standards',
                'bio' => 'Checks compliance requirements and consistency of published academic content.',
                'years_of_experience' => 7,
            ],
        ];

        foreach ($auditors as $auditorData) {
            $user = User::updateOrCreate(
                ['email' => $auditorData['email']],
                [
                    'name' => $auditorData['name'],
                    'email' => $auditorData['email'],
                    'password' => Hash::make('Auditor@123456'),
                    'phone' => $auditorData['phone'],
                    'date_of_birth' => $auditorData['date_of_birth'],
                    'gender' => $auditorData['gender'],
                ]
            );

            $user->syncRoles([$auditorRole->name]);

            $user->auditorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => $auditorData['specialization'],
                    'bio' => $auditorData['bio'],
                    'years_of_experience' => $auditorData['years_of_experience'],
                ]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
