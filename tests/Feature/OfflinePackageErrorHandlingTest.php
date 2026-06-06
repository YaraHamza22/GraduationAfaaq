<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CommunicationModule\Models\OfflineDownloadToken;
use Modules\CommunicationModule\Models\OfflinePackage;
use Modules\UserMangementModule\Models\User;
use Tests\TestCase;

class OfflinePackageErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_by_token_returns_422_for_invalid_token(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/offline-packages/download/not-a-real-token');

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid download token.')
            ->assertJsonPath('data.reason', 'invalid_token');
    }

    public function test_download_by_token_returns_410_for_expired_token(): void
    {
        $user = $this->makeUser();
        $token = $this->makeOfflineTokenForUser($user, [
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/v1/offline-packages/download/{$token->token}");

        $response
            ->assertStatus(410)
            ->assertJsonPath('message', 'Download token has expired.')
            ->assertJsonPath('data.reason', 'expired_token');
    }

    public function test_download_by_token_returns_409_for_device_mismatch(): void
    {
        $user = $this->makeUser();
        $token = $this->makeOfflineTokenForUser($user, [
            'device_id' => 'device-a',
        ]);

        $response = $this->actingAs($user, 'api')
            ->withHeader('X-Device-Id', 'device-b')
            ->getJson("/api/v1/offline-packages/download/{$token->token}");

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Download token is restricted to another device.')
            ->assertJsonPath('data.reason', 'device_mismatch');
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Offline Tester',
            'email' => 'offline.tester@example.com',
            'password' => 'password',
            'phone' => '+963991000000',
            'date_of_birth' => '1995-01-01',
            'gender' => 'male',
        ]);
    }

    private function makeOfflineTokenForUser(User $user, array $overrides = []): OfflineDownloadToken
    {
        $package = OfflinePackage::query()->create([
            'course_id' => 1,
            'created_by' => $user->id,
            'version' => 'v1',
            'manifest' => ['files' => []],
            'file_url' => 'https://example.com/offline/package-v1.zip',
            'is_active' => true,
        ]);

        return OfflineDownloadToken::query()->create(array_merge([
            'offline_package_id' => $package->id,
            'user_id' => $user->id,
            'token' => 'offline-token-'.$package->id.'-'.($overrides['device_id'] ?? 'default'),
            'device_id' => null,
            'expires_at' => now()->addHour(),
        ], $overrides));
    }
}
