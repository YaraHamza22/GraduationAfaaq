<?php

namespace Modules\CommunicationModule\Services\V1;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\CommunicationModule\Models\ExternalIntegration;
use Modules\CommunicationModule\Models\OfflineDownloadToken;
use Modules\CommunicationModule\Models\OfflinePackage;
use Modules\CommunicationModule\Models\OfflineSyncLog;
use Modules\CommunicationModule\Models\SessionAttendance;
use Modules\CommunicationModule\Models\VirtualSession;
use RuntimeException;

class IntegrationService
{
    public function getOAuthRedirectUrl(string $provider, int $userId): array
    {
        $provider = $this->normalizeProvider($provider);
        $cfg = $this->providerConfig($provider);
        $state = base64_encode(json_encode([
            'provider' => $provider,
            'user_id' => $userId,
            'nonce' => Str::random(24),
        ], JSON_THROW_ON_ERROR));

        $query = [
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code',
            'state' => $state,
        ];

        if ($provider === 'google_classroom') {
            $query['access_type'] = 'offline';
            $query['prompt'] = 'consent';
            $query['scope'] = implode(' ', $cfg['scopes'] ?? []);
        }

        return [
            'provider' => $provider,
            'state' => $state,
            'authorize_url' => $cfg['authorize_url'].'?'.http_build_query($query),
        ];
    }

    public function exchangeAuthorizationCode(string $provider, int $userId, string $code): ExternalIntegration
    {
        $provider = $this->normalizeProvider($provider);
        $cfg = $this->providerConfig($provider);
        $response = $this->httpClient()->asForm()->post($cfg['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => $cfg['redirect_uri'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Failed to exchange {$provider} OAuth code.");
        }

        $payload = $response->json();

        $externalAccountId = $payload['user_id']
            ?? Arr::get($payload, 'id_token.sub')
            ?? null;

        return ExternalIntegration::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => $provider,
            ],
            [
                'external_account_id' => $externalAccountId,
                'access_token' => $payload['access_token'] ?? null,
                'refresh_token' => $payload['refresh_token'] ?? null,
                'expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : null,
                'is_active' => true,
            ]
        );
    }

    public function publishSession(VirtualSession $session): VirtualSession
    {
        if ($session->provider === 'afaq_live') {
            $providerPayload = $this->publishAfaqLiveSession($session);

            $session->update([
                'status' => 'published',
                'provider_event_id' => (string) ($providerPayload['provider_event_id'] ?? Str::uuid()),
                'join_url' => (string) ($providerPayload['join_url'] ?? $session->join_url),
                'metadata' => array_merge((array) $session->metadata, [
                    'provider_payload' => $providerPayload['raw'] ?? [],
                    'room_id' => $providerPayload['room_id'] ?? "afaq-session-{$session->id}",
                ]),
            ]);

            return $session->fresh();
        }

        // Manual flow: if admin already provided a meeting link, publish without provider API calls.
        if (filled($session->join_url)) {
            $session->update([
                'status' => 'published',
                'provider_event_id' => (string) ($session->provider_event_id ?: Str::uuid()),
                'metadata' => array_merge((array) $session->metadata, [
                    'provider_payload' => [
                        'mode' => 'manual_link',
                    ],
                ]),
            ]);

            return $session->fresh();
        }

        $integration = ExternalIntegration::query()->findOrFail($session->integration_id);
        $integration = $this->refreshTokenIfExpired($integration);

        $providerPayload = $session->provider === 'zoom'
            ? $this->createZoomMeeting($integration, $session)
            : $this->createGoogleClassroomSession($integration, $session);

        $session->update([
            'status' => 'published',
            'provider_event_id' => (string) ($providerPayload['provider_event_id'] ?? Str::uuid()),
            'join_url' => (string) ($providerPayload['join_url'] ?? $session->join_url),
            'metadata' => array_merge((array) $session->metadata, [
                'provider_payload' => $providerPayload['raw'] ?? [],
            ]),
        ]);

        return $session->fresh();
    }

    public function cancelSession(VirtualSession $session): VirtualSession
    {
        if ($session->provider === 'afaq_live') {
            $session->update(['status' => 'cancelled']);
            return $session->fresh();
        }

        if (! $session->integration_id) {
            $session->update(['status' => 'cancelled']);
            return $session->fresh();
        }

        $integration = ExternalIntegration::query()->findOrFail($session->integration_id);
        $integration = $this->refreshTokenIfExpired($integration);

        if ($session->provider_event_id) {
            if ($session->provider === 'zoom') {
                $this->cancelZoomMeeting($integration, $session->provider_event_id);
            } elseif (in_array($session->provider, ['google_classroom', 'google_meet'], true)) {
                $this->archiveGoogleClassroomCourseWork($integration, $session);
            }
        }

        $session->update(['status' => 'cancelled']);
        return $session->fresh();
    }

    public function processWebhook(string $provider, array $payload): void
    {
        $provider = $this->normalizeProvider($provider);

        if ($provider === 'zoom') {
            $event = (string) ($payload['event'] ?? '');
            $object = (array) Arr::get($payload, 'payload.object', []);
            $meetingId = (string) ($object['id'] ?? '');

            if ($meetingId === '') {
                return;
            }

            $status = match ($event) {
                'meeting.started' => 'published',
                'meeting.ended', 'meeting.deleted' => 'cancelled',
                default => null,
            };

            if ($status) {
                VirtualSession::query()
                    ->where('provider', 'zoom')
                    ->where('provider_event_id', $meetingId)
                    ->update(['status' => $status]);
            }
        }
    }

    public function storeAttendance(VirtualSession $session, array $payload): SessionAttendance
    {
        return SessionAttendance::query()->updateOrCreate(
            [
                'virtual_session_id' => $session->id,
                'user_id' => $payload['user_id'],
            ],
            [
                'joined_at' => $payload['joined_at'] ?? null,
                'left_at' => $payload['left_at'] ?? null,
                'duration_minutes' => $payload['duration_minutes'] ?? 0,
            ]
        );
    }

    public function issueOfflineToken(OfflinePackage $package, array $payload): OfflineDownloadToken
    {
        return OfflineDownloadToken::query()->create([
            'offline_package_id' => $package->id,
            'user_id' => $payload['user_id'],
            'token' => Str::random(80),
            'device_id' => $payload['device_id'] ?? null,
            'expires_at' => $payload['expires_at'],
        ]);
    }

    public function validateOfflineDownloadToken(string $token, int $userId, ?string $deviceId = null): array
    {
        $offlineToken = OfflineDownloadToken::query()
            ->where('token', $token)
            ->where('user_id', $userId)
            ->first();

        if (! $offlineToken) {
            throw new RuntimeException('Invalid download token.');
        }

        if ($offlineToken->revoked_at) {
            throw new RuntimeException('Download token is revoked.');
        }

        if (Carbon::parse($offlineToken->expires_at)->isPast()) {
            throw new RuntimeException('Download token has expired.');
        }

        if ($offlineToken->device_id && $deviceId && $offlineToken->device_id !== $deviceId) {
            throw new RuntimeException('Token is restricted to another device.');
        }

        if (! $offlineToken->device_id && $deviceId) {
            $offlineToken->update(['device_id' => $deviceId]);
        }

        $package = OfflinePackage::query()->findOrFail($offlineToken->offline_package_id);

        return [
            'package_id' => $package->id,
            'course_id' => $package->course_id,
            'version' => $package->version,
            'manifest' => $package->manifest,
            'file_url' => $package->file_url,
            'expires_at' => $offlineToken->expires_at,
        ];
    }

    public function storeSyncLog(array $payload): OfflineSyncLog
    {
        return OfflineSyncLog::query()->create($payload);
    }

    public function storeSyncLogsBatch(int $userId, string $deviceId, array $entries): array
    {
        $stored = 0;
        $duplicates = 0;
        $rows = [];

        foreach ($entries as $entry) {
            $clientEventId = (string) $entry['client_event_id'];
            $existing = OfflineSyncLog::query()
                ->where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->where('client_event_id', $clientEventId)
                ->first();

            if ($existing) {
                $duplicates++;
                $rows[] = $existing;
                continue;
            }

            $row = OfflineSyncLog::query()->create([
                'user_id' => $userId,
                'offline_package_id' => $entry['offline_package_id'] ?? null,
                'device_id' => $deviceId,
                'client_event_id' => $clientEventId,
                'action' => $entry['action'],
                'payload' => $entry['payload'] ?? null,
                'created_at' => $entry['occurred_at'] ?? now(),
            ]);

            $stored++;
            $rows[] = $row;
        }

        return [
            'stored' => $stored,
            'duplicates' => $duplicates,
            'entries' => $rows,
        ];
    }

    public function resolveOfflineDelta(int $courseId, ?string $currentVersion): array
    {
        $latest = OfflinePackage::query()
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $latest) {
            return [
                'has_update' => false,
                'course_id' => $courseId,
                'current_version' => $currentVersion,
                'latest_version' => null,
                'package' => null,
            ];
        }

        $hasUpdate = $currentVersion === null || $currentVersion !== (string) $latest->version;
        $manifestJson = json_encode($latest->manifest ?? [], JSON_UNESCAPED_UNICODE);

        return [
            'has_update' => $hasUpdate,
            'course_id' => $courseId,
            'current_version' => $currentVersion,
            'latest_version' => (string) $latest->version,
            'package' => [
                'id' => $latest->id,
                'version' => $latest->version,
                'file_url' => $latest->file_url,
                'manifest' => $latest->manifest,
                'manifest_checksum' => hash('sha256', $manifestJson ?: '[]'),
                'updated_at' => optional($latest->updated_at)->toIso8601String(),
            ],
        ];
    }

    private function providerConfig(string $provider): array
    {
        $provider = $this->normalizeProvider($provider);
        $cfg = (array) config("communicationmodule.integrations.{$provider}");
        if ($cfg === [] || blank($cfg['client_id'] ?? null) || blank($cfg['client_secret'] ?? null)) {
            throw new RuntimeException("Provider {$provider} is not configured.");
        }

        return $cfg;
    }

    private function refreshTokenIfExpired(ExternalIntegration $integration): ExternalIntegration
    {
        if (! $integration->refresh_token || ! $integration->expires_at || $integration->expires_at->isFuture()) {
            return $integration;
        }

        $cfg = $this->providerConfig((string) $integration->provider);
        $response = $this->httpClient()->asForm()->post($cfg['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $integration->refresh_token,
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Failed to refresh {$integration->provider} access token.");
        }

        $payload = $response->json();
        $integration->update([
            'access_token' => $payload['access_token'] ?? $integration->access_token,
            'refresh_token' => $payload['refresh_token'] ?? $integration->refresh_token,
            'expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : $integration->expires_at,
            'is_active' => true,
        ]);

        return $integration->fresh();
    }

    private function createZoomMeeting(ExternalIntegration $integration, VirtualSession $session): array
    {
        $cfg = $this->providerConfig('zoom');
        $response = $this->httpClient()->withToken($integration->access_token)
            ->post(rtrim($cfg['api_base_url'], '/').'/users/me/meetings', [
                'topic' => $session->title,
                'agenda' => $session->description,
                'type' => 2,
                'start_time' => $session->starts_at?->toIso8601String(),
                'duration' => $session->ends_at && $session->starts_at
                    ? max(1, $session->starts_at->diffInMinutes($session->ends_at))
                    : null,
                'timezone' => config('app.timezone', 'UTC'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to create Zoom meeting.');
        }

        $payload = $response->json();

        return [
            'provider_event_id' => (string) ($payload['id'] ?? ''),
            'join_url' => (string) ($payload['join_url'] ?? ''),
            'raw' => $payload,
        ];
    }

    private function publishAfaqLiveSession(VirtualSession $session): array
    {
        $cfg = (array) config('communicationmodule.integrations.afaq_live');
        $baseUrl = $this->normalizeAfaqLiveBaseUrl((string) ($cfg['live_base_url'] ?? 'https://afaaq.com/live'));
        $roomId = (string) data_get($session->metadata, 'room_id', "afaq-session-{$session->id}");
        $joinUrl = $baseUrl.'?sessionId='.$session->id.'&room='.urlencode($roomId);

        return [
            'provider_event_id' => (string) ($session->provider_event_id ?: Str::uuid()),
            'join_url' => $joinUrl,
            'room_id' => $roomId,
            'raw' => [
                'room_id' => $roomId,
                'live_base_url' => $baseUrl,
                'quality' => 'hd',
            ],
        ];
    }

    private function normalizeAfaqLiveBaseUrl(string $rawBaseUrl): string
    {
        $baseUrl = trim($rawBaseUrl);

        if (str_contains($baseUrl, '=')) {
            $candidate = trim((string) Str::afterLast($baseUrl, '='));
            if (preg_match('#^https?://#i', $candidate) === 1) {
                $baseUrl = $candidate;
            }
        }

        return rtrim($baseUrl, '/');
    }

    private function cancelZoomMeeting(ExternalIntegration $integration, string $meetingId): void
    {
        $cfg = $this->providerConfig('zoom');
        $this->httpClient()->withToken($integration->access_token)
            ->delete(rtrim($cfg['api_base_url'], '/')."/meetings/{$meetingId}");
    }

    private function createGoogleClassroomSession(ExternalIntegration $integration, VirtualSession $session): array
    {
        $cfg = $this->providerConfig('google_classroom');
        $courseId = data_get($session->metadata, 'google_course_id');
        if (! $courseId) {
            throw new RuntimeException('google_course_id is required in session metadata.');
        }

        $response = $this->httpClient()->withToken($integration->access_token)
            ->post(rtrim($cfg['api_base_url'], '/')."/courses/{$courseId}/courseWork", [
                'title' => $session->title,
                'description' => $session->description,
                'workType' => 'ASSIGNMENT',
                'state' => 'PUBLISHED',
                'dueDate' => [
                    'year' => $session->ends_at?->year,
                    'month' => $session->ends_at?->month,
                    'day' => $session->ends_at?->day,
                ],
                'dueTime' => [
                    'hours' => $session->ends_at?->hour,
                    'minutes' => $session->ends_at?->minute,
                    'seconds' => $session->ends_at?->second,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to create Google Classroom coursework.');
        }

        $payload = $response->json();

        return [
            'provider_event_id' => (string) ($payload['id'] ?? ''),
            'join_url' => (string) ($payload['alternateLink'] ?? ''),
            'raw' => $payload,
        ];
    }

    private function archiveGoogleClassroomCourseWork(ExternalIntegration $integration, VirtualSession $session): void
    {
        $cfg = $this->providerConfig('google_classroom');
        $courseId = data_get($session->metadata, 'google_course_id');
        if (! $courseId || ! $session->provider_event_id) {
            return;
        }

        $this->httpClient()->withToken($integration->access_token)->patch(
            rtrim($cfg['api_base_url'], '/')."/courses/{$courseId}/courseWork/{$session->provider_event_id}",
            [
                'state' => 'DELETED',
            ]
        );
    }

    private function httpClient(): PendingRequest
    {
        $caBundle = config('communicationmodule.http.ca_bundle');

        if (is_string($caBundle) && trim($caBundle) !== '') {
            $caBundlePath = trim($caBundle);
            if (! is_file($caBundlePath)) {
                throw new RuntimeException("SSL CA bundle not found: \"{$caBundlePath}\"");
            }

            return Http::withOptions(['verify' => $caBundlePath]);
        }

        return Http::withOptions(['verify' => true]);
    }

    private function normalizeProvider(string $provider): string
    {
        return $provider === 'google_meet' ? 'google_classroom' : $provider;
    }
}
