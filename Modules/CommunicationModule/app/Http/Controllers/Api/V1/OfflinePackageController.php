<?php

namespace Modules\CommunicationModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\CommunicationModule\Http\Requests\Offline\IssueDownloadTokenRequest;
use Modules\CommunicationModule\Http\Requests\Offline\StoreOfflinePackageRequest;
use Modules\CommunicationModule\Http\Requests\Offline\StoreOfflineSyncBatchRequest;
use Modules\CommunicationModule\Http\Requests\Offline\StoreOfflineSyncLogRequest;
use Modules\CommunicationModule\Models\OfflineDownloadToken;
use Modules\CommunicationModule\Models\OfflinePackage;
use Modules\CommunicationModule\Services\V1\IntegrationService;
use Throwable;

class OfflinePackageController extends Controller
{
    public function __construct(private IntegrationService $integrationService)
    {
    }

    public function index()
    {
        $packages = OfflinePackage::query()->latest()->paginate(15);
        return self::paginated($packages, 'Offline packages fetched successfully.');
    }

    public function store(StoreOfflinePackageRequest $request)
    {
        $package = OfflinePackage::query()->create($request->validated() + ['created_by' => Auth::id()]);
        return self::success($package, 'Offline package created successfully.', 201);
    }

    public function show(OfflinePackage $offlinePackage)
    {
        return self::success($offlinePackage, 'Offline package fetched successfully.');
    }

    public function issueToken(IssueDownloadTokenRequest $request, OfflinePackage $offlinePackage)
    {
        $this->authorize('update', $offlinePackage);
        $token = $this->integrationService->issueOfflineToken($offlinePackage, $request->validated());
        return self::success($token, 'Offline download token issued successfully.', 201);
    }

    public function revokeToken(OfflineDownloadToken $offlineDownloadToken)
    {
        $this->authorize('revoke', $offlineDownloadToken);
        $offlineDownloadToken->update(['revoked_at' => now()]);
        return self::success($offlineDownloadToken->fresh(), 'Offline download token revoked successfully.');
    }

    public function storeSyncLog(StoreOfflineSyncLogRequest $request)
    {
        $payload = $request->validated();
        $payload['user_id'] = Auth::id();
        $log = $this->integrationService->storeSyncLog($payload);
        return self::success($log, 'Offline sync log stored successfully.', 201);
    }

    public function storeSyncLogBatch(StoreOfflineSyncBatchRequest $request)
    {
        $payload = $request->validated();
        $result = $this->integrationService->storeSyncLogsBatch(
            (int) Auth::id(),
            (string) $payload['device_id'],
            (array) $payload['entries']
        );

        return self::success($result, 'Offline sync batch processed successfully.', 201);
    }

    public function delta(Request $request, int $courseId)
    {
        $currentVersion = $request->query('version');
        $result = $this->integrationService->resolveOfflineDelta($courseId, $currentVersion ? (string) $currentVersion : null);

        return self::success($result, 'Offline delta resolved successfully.');
    }

    public function downloadByToken(Request $request, ?string $token = null)
    {
        $token = $token ?: (string) $request->query('token', '');
        $token = ltrim(trim($token), ':');
        $deviceId = $request->header('X-Device-Id') ?: $request->query('device_id');

        try {
            if ($token === '') {
                return self::error('Download token is required.', 422);
            }

            $data = $this->integrationService->validateOfflineDownloadToken(
                $token,
                (int) Auth::id(),
                $deviceId ? (string) $deviceId : null
            );

            return self::success($data, 'Offline package token validated successfully.');
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 422);
        }
    }
}
