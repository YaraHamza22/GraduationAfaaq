<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\UserMangementModule\Models\User;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\CourseCategory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;
    private CourseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);

        $permissions = [
            'list-courses', 'show-course', 'create-course',
            'update-course', 'delete-course',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => 'Password123!',
            'phone'         => '+963999999999',
            'date_of_birth'  => '2000-01-01',
            'gender'         => 'male',
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->givePermissionTo($permissions);

        $this->token = auth('api')->login($this->admin);

        $this->category = CourseCategory::create([
            'name'      => ['en' => 'Programming'],
            'slug'      => 'programming',
            'is_active' => true,
        ]);
    }

    private function coursePayload(array $overrides = []): array
    {
        return array_merge([
            'title'                => ['en' => 'Laravel Course', 'ar' => 'كورس لارافيل'],
            'description'          => ['en' => 'Learn Laravel'],
            'course_category_id'   => $this->category->course_category_id,
            'actual_duration_hours'=> 10,
            'language'             => 'en',
            'difficulty_level'     => 'beginner',
            'course_delivery_type' => 'self_paced',
            'min_score_to_pass'    => 60,
            'is_offline_available' => false,
        ], $overrides);
    }

    // ─── LIST ────────────────────────────────────────────────────────────────

    public function test_can_list_courses(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    // ─── STORE ───────────────────────────────────────────────────────────────

    public function test_can_create_course(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/courses', $this->coursePayload())
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('courses', ['slug' => 'laravel-course']);
    }

    public function test_cannot_create_course_without_title(): void
    {
        $payload = $this->coursePayload();
        unset($payload['title']);

        $this->withToken($this->token)
            ->postJson('/api/v1/courses', $payload)
            ->assertStatus(422);
    }

    public function test_cannot_create_course_with_invalid_category(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/courses', $this->coursePayload(['course_category_id' => 99999]))
            ->assertStatus(422);
    }

    // ─── SHOW ────────────────────────────────────────────────────────────────

    public function test_can_show_course(): void
    {
        $course = $this->createCourse();

        $this->withToken($this->token)
            ->getJson("/api/v1/courses/{$course->slug}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_returns_404_for_nonexistent_course(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/courses/nonexistent-slug')
            ->assertStatus(404);
    }

    // ─── UPDATE ──────────────────────────────────────────────────────────────

    public function test_can_update_course(): void
    {
        $course = $this->createCourse();

        $this->withToken($this->token)
            ->putJson("/api/v1/courses/{$course->slug}", [
                'actual_duration_hours' => 20,
                'difficulty_level'      => 'intermediate',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    // ─── PUBLISH / UNPUBLISH ─────────────────────────────────────────────────

    public function test_cannot_publish_course_without_instructor(): void
    {
        $course = $this->createCourse();

        $this->withToken($this->token)
            ->postJson("/api/v1/courses/{$course->slug}/publish")
            ->assertStatus(422);
    }

    public function test_can_unpublish_course(): void
    {
        $course = $this->createCourse(['status' => 'published']);

        $this->withToken($this->token)
            ->postJson("/api/v1/courses/{$course->slug}/unpublish")
            ->assertOk();
    }

    // ─── PUBLISHABILITY CHECK ────────────────────────────────────────────────

    public function test_can_check_course_publishability(): void
    {
        $course = $this->createCourse();

        $this->withToken($this->token)
            ->getJson("/api/v1/courses/{$course->slug}/publishability")
            ->assertOk()
            ->assertJsonStructure(['data' => ['is_publishable', 'reasons']]);
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    public function test_can_delete_course_with_no_active_enrollments(): void
    {
        $course = $this->createCourse();

        $this->withToken($this->token)
            ->deleteJson("/api/v1/courses/{$course->slug}")
            ->assertOk();
    }

    // ─── DURATION ────────────────────────────────────────────────────────────

    public function test_can_get_course_duration(): void
    {
        $course = $this->createCourse();

        $this->withToken($this->token)
            ->getJson("/api/v1/courses/{$course->slug}/duration")
            ->assertOk()
            ->assertJsonStructure(['data' => ['course_id', 'duration_hours']]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function createCourse(array $overrides = []): Course
    {
        return Course::create(array_merge([
            'title'                => ['en' => 'Laravel Course', 'ar' => 'كورس لارافيل'],
            'slug'                 => 'laravel-course',
            'course_category_id'   => $this->category->course_category_id,
            'actual_duration_hours'=> 10,
            'status'               => 'draft',
            'created_by'           => $this->admin->id,
        ], $overrides));
    }
}
