<?php

namespace Tests\Feature;

use Tests\TestCase;
use Modules\UserMangementModule\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class InstructorWhiteBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_instructor_without_media(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'api']);

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $admin->assignRole('super-admin');

        $token = auth('api')->login($admin);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/super-admin/instructors', [
                'name' => 'Instructor Test',
                'email' => 'instructor@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'phone' => '+963998887766',
                'date_of_birth' => '2000-01-30',
                'gender' => 'female',
                'years_of_experience' => 5,
                'address' => 'Damascus',
                'country' => 'Syria',
                'bio' => 'Backend instructor',
                'specialization' => 'Laravel',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'instructor@example.com',
        ]);

        $created = User::where('email', 'instructor@example.com')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('instructor'));
        $this->assertNotNull($created->instructorProfile);
    }
}