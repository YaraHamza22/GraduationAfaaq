<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LearningModule\Models\CourseCategory;
use Modules\UserMangementModule\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CourseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);

        $permissions = [
            'list-categories', 'show-category',
            'create-category', 'update-category', 'delete-category',
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
    }

    public function test_can_list_course_categories(): void
    {
        CourseCategory::create([
            'name' => ['en' => 'Programming', 'ar' => 'برمجة'],
            'slug' => 'programming',
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/course-categories')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_create_course_category(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/course-categories', [
                'name' => ['en' => 'Data Science', 'ar' => 'علم البيانات'],
                'description' => ['en' => 'Data related courses'],
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('course_categories', ['slug' => 'data-science']);
    }

    public function test_cannot_create_category_without_name(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/course-categories', [
                'is_active' => true,
            ])
            ->assertStatus(422);
    }

    public function test_can_show_course_category(): void
    {
        $category = CourseCategory::create([
            'name' => ['en' => 'Web Dev'],
            'slug' => 'web-dev',
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/course-categories/{$category->course_category_id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_update_course_category(): void
    {
        $category = CourseCategory::create([
            'name' => ['en' => 'Mobile'],
            'slug' => 'mobile',
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/course-categories/{$category->course_category_id}", [
                'name' => ['en' => 'Mobile Development'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_activate_and_deactivate_category(): void
    {
        $category = CourseCategory::create([
            'name' => ['en' => 'DevOps'],
            'slug' => 'devops',
            'is_active' => false,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/course-categories/{$category->course_category_id}/activate")
            ->assertOk();

        $this->assertDatabaseHas('course_categories', [
            'course_category_id' => $category->course_category_id,
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/course-categories/{$category->course_category_id}/deactivate")
            ->assertOk();

        $this->assertDatabaseHas('course_categories', [
            'course_category_id' => $category->course_category_id,
            'is_active' => false,
        ]);
    }
}
