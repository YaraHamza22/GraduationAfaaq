<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\CourseCategory;
use Modules\LearningModule\Models\Lesson;
use Modules\LearningModule\Models\Unit;
use Modules\UserMangementModule\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnitLessonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;
    private Course $course;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);

        $permissions = [
            'list-units', 'show-unit', 'create-unit', 'update-unit', 'delete-unit',
            'list-lessons', 'show-lesson', 'create-lesson', 'update-lesson', 'delete-lesson',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'Password123!',
            'phone' => '+963999999999',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->givePermissionTo($permissions);
        $this->token = auth('api')->login($this->admin);

        $category = CourseCategory::create([
            'name' => ['en' => 'Tech'],
            'slug' => 'tech',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'title' => ['en' => 'Test Course'],
            'slug' => 'test-course',
            'course_category_id' => $category->course_category_id,
            'actual_duration_hours' => 5,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->unit = Unit::create([
            'course_id' => $this->course->course_id,
            'title' => ['en' => 'Unit 1'],
            'slug' => 'unit-1',
            'unit_order' => 1,
            'actual_duration_minutes' => 60,
        ]);
    }

    public function test_can_list_units(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/units')
            ->assertOk();
    }

    public function test_can_list_units_by_course(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/courses/{$this->course->slug}/units")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_create_unit(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/units', [
                'course_id' => $this->course->course_id,
                'title' => ['en' => 'Unit 2', 'ar' => 'Ø§Ù„ÙˆØ­Ø¯Ø© 2'],
                'description' => ['en' => 'Second unit'],
                'actual_duration_minutes' => 90,
                'unit_order' => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    public function test_cannot_create_unit_without_title(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/units', [
                'course_id' => $this->course->course_id,
                'actual_duration_minutes' => 60,
            ])
            ->assertStatus(422);
    }

    public function test_can_show_unit(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/units/{$this->unit->unit_id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_update_unit(): void
    {
        $this->withToken($this->token)
            ->putJson("/api/v1/units/{$this->unit->unit_id}", [
                'title' => ['en' => 'Unit 1 Updated'],
                'actual_duration_minutes' => 120,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_list_lessons(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/lessons')
            ->assertOk();
    }

    public function test_can_list_lessons_by_unit(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/units/{$this->unit->unit_id}/lessons")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_create_lesson(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/lessons', [
                'unit_id' => $this->unit->unit_id,
                'title' => ['en' => 'Lesson 1', 'ar' => 'Ø§Ù„Ø¯Ø±Ø³ 1'],
                'description' => ['en' => 'First lesson'],
                'lesson_type' => 'video',
                'actual_duration_minutes' => 30,
                'lesson_order' => 1,
                'is_required' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    public function test_cannot_create_lesson_without_required_fields(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/lessons', [
                'unit_id' => $this->unit->unit_id,
            ])
            ->assertStatus(422);
    }

    public function test_cannot_create_lesson_with_invalid_type(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/lessons', [
                'unit_id' => $this->unit->unit_id,
                'title' => ['en' => 'Lesson'],
                'lesson_type' => 'invalid_type',
                'actual_duration_minutes' => 30,
            ])
            ->assertStatus(422);
    }

    public function test_can_show_lesson(): void
    {
        $lesson = $this->createLesson();

        $this->withToken($this->token)
            ->getJson("/api/v1/lessons/{$lesson->lesson_id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_update_lesson(): void
    {
        $lesson = $this->createLesson();

        $this->withToken($this->token)
            ->putJson("/api/v1/lessons/{$lesson->lesson_id}", [
                'title' => ['en' => 'Updated Lesson'],
                'actual_duration_minutes' => 45,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_delete_lesson(): void
    {
        $lesson = $this->createLesson();

        $this->withToken($this->token)
            ->deleteJson("/api/v1/lessons/{$lesson->lesson_id}")
            ->assertOk();
    }

    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create(array_merge([
            'unit_id' => $this->unit->unit_id,
            'title' => ['en' => 'Test Lesson'],
            'slug' => 'test-lesson-'.uniqid(),
            'lesson_type' => 'video',
            'actual_duration_minutes' => 30,
            'lesson_order' => 1,
            'is_required' => true,
        ], $overrides));
    }
}
