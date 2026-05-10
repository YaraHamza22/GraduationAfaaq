<?php

namespace Modules\UserMangementModule\Database\Seeders\RolesAndPermissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Auditor role: read-only oversight of educational catalog (courses → units → lessons, assessments, categories).
 *
 * Maps to functional requirements (oversight / مراجع المحتوى):
 * - Login → JWT via {@see \Modules\UserMangementModule\Http\Controllers\Api\V1\AuthController} (no permission).
 * - View courses (list + detail) → list-courses, show-course.
 * - View units + details → list-units, show-unit.
 * - View lessons + details / uploaded materials surfaced on lesson resources → list-lessons, show-lesson.
 * - Review workflow → filter courses with `status=review` on list courses; structured review APIs may extend later.
 * - In-app notifications → GET /api/v1/notifications (no permission middleware; scoped to authenticated user).
 *
 * Structure mirrors {@see InstructorRoleSeeder} / {@see StudentRoleSeeder}: grouped permission strings, no CUD keys.
 */
class AuditorRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // course categories
            'list-categories',
            'show-category',

            // courses
            'list-courses',
            'show-course',

            // units
            'list-units',
            'show-unit',

            // lessons
            'list-lessons',
            'show-lesson',

            // quizzes (curriculum-linked assessments)
            'list-quiz',
            'show-quiz',

            // questions
            'list-questions',
            'show-question',

            // question options
            'list-options',
            'show-option',

            // مراجعة محتوى الكورس (سجل + تسجيل مراجعة)
            'list-reviews',
            'create-review',

            // allow auditor to send notification to super-admin (checked in controller)
            'create-notification',
        ];

        $role = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'api']);
        $role->syncPermissions($permissions);
    }
}
