<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\UserMangementModule\Models\User;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\CourseCategory;
use Modules\LearningModule\Models\Enrollment;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private string $adminToken;
    private string $studentToken;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super-admin', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        $enrollmentPerms = ['list-enrollments', 'show-enrollment', 'create-enrollment', 'update-enrollment', 'delete-enrollment'];
        foreach ($enrollmentPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $this->admin = User::create([
            'name'          => 'Admin',
            'email'         => 'admin@test.com',
            'password'      => 'Password123!',
            'phone'         => '+963999999999',
            'date_of_birth' => '2000-01-01',
            'gender'        => 'male',
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->givePermissionTo($enrollmentPerms);
        $this->adminToken = auth('api')->login($this->admin);

        $this->student = User::create([
            'name'          => 'Student',
            'email'         => 'student@test.com',
            'password'      => 'Password123!',
            'phone'         => '+963999999998',
            'date_of_birth' => '2000-01-01',
            'gender'        => 'female',
        ]);
        $this->student->assignRole('student');
        $this->studentToken = auth('api')->login($this->student);

        $category = CourseCategory::create([
            'name' => ['en' => 'Tech'], 'slug' => 'tech', 'is_active' => true,
        ]);

        $this->course = Course::create([
            'title'                => ['en' => 'Laravel Course'],
            'slug'                 => 'laravel-course',
            'course_category_id'   => $category->course_category_id,
            'actual_duration_hours'=> 10,
            'status'               => 'published',
            'created_by'           => $this->admin->id,
        ]);
    }

    public function test_admin_can_list_enrollments(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/v1/enrollments')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_admin_can_create_enrollment(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/enrollments', [
                'course_id'         => $this->course->course_id,
                'learner_id'        => $this->student->id,
                'enrollment_type'   => 'self_enrolled',
                'enrollment_status' => 'active',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    public function test_can_show_enrollment(): void
    {
        $enrollment = Enrollment::create([
            'course_id'         => $this->course->course_id,
            'learner_id'        => $this->student->id,
            'enrollment_status' => 'active',
            'enrollment_type'   => 'self_enrolled',
            'enrolled_by'       => $this->admin->id,
            'enrolled_at'       => now(),
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/v1/enrollments/{$enrollment->enrollment_id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_admin_can_update_enrollment_status(): void
    {
        $enrollment = Enrollment::create([
            'course_id'         => $this->course->course_id,
            'learner_id'        => $this->student->id,
            'enrollment_status' => 'active',
            'enrollment_type'   => 'self_enrolled',
            'enrolled_by'       => $this->admin->id,
            'enrolled_at'       => now(),
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/v1/enrollments/{$enrollment->enrollment_id}/status", [
                'enrollment_status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('enrollments', [
            'enrollment_id'     => $enrollment->enrollment_id,
            'enrollment_status' => 'completed',
        ]);
    }

    public function test_student_can_view_my_learning(): void
    {
        Enrollment::create([
            'course_id'         => $this->course->course_id,
            'learner_id'        => $this->student->id,
            'enrollment_status' => 'active',
            'enrollment_type'   => 'self_enrolled',
            'enrolled_by'       => $this->student->id,
            'enrolled_at'       => now(),
        ]);

        $this->withToken($this->studentToken)
            ->getJson('/api/v1/my-learning')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
