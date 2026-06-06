<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\UserMangementModule\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportingSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_materialize_snapshot_endpoint_requires_super_admin_role(): void
    {
        $student = $this->createUser();

        $this->actingAs($student, 'api')
            ->postJson('/api/v1/super-admin/snapshots/materialize', [
                'snapshot_date' => '2026-04-28',
            ])
            ->assertForbidden();
    }

    private function createUser(): User
    {
        $email = 'reporting-'.Str::lower(Str::random(8)).'@example.com';

        DB::table('users')->insert([
            'name' => 'Reporting Tester',
            'email' => $email,
            'password' => bcrypt('password'),
            'phone' => '0000000000',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'address' => 'Test Address',
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->where('email', $email)->firstOrFail();
    }
}
