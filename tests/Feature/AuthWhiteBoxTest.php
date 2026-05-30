<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\UserMangementModule\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthWhiteBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.defaults.guard' => 'api',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createTestUser('student@example.com');

        $role = Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'api',
        ]);

        $user->assignRole($role);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'status',
                    'user',
                    'role',
                    'token',
                ],
            ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = $this->createTestUser('wrong@example.com');

        $role = Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'api',
        ]);

        $user->assignRole($role);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_student_register_creates_user_and_student_profile(): void
    {
        Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'api',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Student',
            'email' => 'newstudent@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+963998887766',
            'date_of_birth' => '2005-01-30',
            'gender' => 'female',
            'education_level' => 'highschool',
            'address' => 'Damascus',
            'country' => 'Syria',
            'bio' => 'Computer science student',
            'specialization' => 'Software Engineering',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'newstudent@example.com',
        ]);

        $user = User::where('email', 'newstudent@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertNotNull($user->studentProfile);
    }

    private function createTestUser(string $email): User
    {
        return User::create([
            'name' => 'Test Student',
            'email' => $email,
            'password' => 'Password123!',
            'phone' => '0999999999',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'address' => 'Test Address',
        ]);
    }
}