<?php

namespace Modules\CommunicationModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
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
        try {
            $packages = OfflinePackage::query()->latest()->paginate(15);
            return self::paginated($packages, 'Offline packages fetched successfully.');
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to fetch offline packages.');
        }
    }

    public function store(StoreOfflinePackageRequest $request)
    {
        try {
            $payload = $request->validated();

            if ($request->hasFile('package_file')) {
                $payload['file_url'] = $this->storeOfflinePackageFile($request);
            }

            unset($payload['package_file']);

            $package = OfflinePackage::query()->create($payload + ['created_by' => Auth::id()]);
            return self::success($package, 'Offline package created successfully.', 201);
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to create offline package.');
        }
    }

    public function show(OfflinePackage $offlinePackage)
    {
        try {
            return self::success($offlinePackage, 'Offline package fetched successfully.');
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to fetch offline package.');
        }
    }

    public function issueToken(IssueDownloadTokenRequest $request, OfflinePackage $offlinePackage)
    {
        try {
            $this->authorize('download', $offlinePackage);
            $token = $this->integrationService->issueOfflineToken($offlinePackage, $request->validated());
            return self::success($token, 'Offline download token issued successfully.', 201);
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to issue offline download token.');
        }
    }

    public function revokeToken(OfflineDownloadToken $offlineDownloadToken)
    {
        try {
            $this->authorize('revoke', $offlineDownloadToken);
            $offlineDownloadToken->update(['revoked_at' => now()]);
            return self::success($offlineDownloadToken->fresh(), 'Offline download token revoked successfully.');
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to revoke offline download token.');
        }
    }

    public function storeSyncLog(StoreOfflineSyncLogRequest $request)
    {
        try {
            $payload = $request->validated();
            $payload['user_id'] = Auth::id();
            $log = $this->integrationService->storeSyncLog($payload);
            return self::success($log, 'Offline sync log stored successfully.', 201);
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to store offline sync log.');
        }
    }

    public function storeSyncLogBatch(StoreOfflineSyncBatchRequest $request)
    {
        try {
            $payload = $request->validated();
            $result = $this->integrationService->storeSyncLogsBatch(
                (int) Auth::id(),
                (string) $payload['device_id'],
                (array) $payload['entries']
            );

            return self::success($result, 'Offline sync batch processed successfully.', 201);
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to process offline sync batch.');
        }
    }

    public function delta(Request $request, int $courseId)
    {
        try {
            $currentVersion = $request->query('version');
            $result = $this->integrationService->resolveOfflineDelta($courseId, $currentVersion ? (string) $currentVersion : null);

            return self::success($result, 'Offline delta resolved successfully.');
        } catch (Throwable $e) {
            return $this->handleOfflineException($e, 'Failed to resolve offline delta.');
        }
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
            return $this->handleOfflineException($e, 'Failed to validate offline download token.');
        }
    }

    private function storeOfflinePackageFile(StoreOfflinePackageRequest $request): string
    {
        $uploadedFile = $request->file('package_file');
        $courseId = (int) $request->input('course_id');
        $version = (string) $request->input('version', 'package');
        $safeVersion = Str::slug($version, '-');
        $extension = $uploadedFile?->getClientOriginalExtension() ?: 'zip';
        $fileName = "course-{$courseId}-{$safeVersion}.{$extension}";
        $storedPath = $uploadedFile->storeAs('offline', $fileName, 'public');
        $publicPath = Storage::disk('public')->url($storedPath);

        if (Str::startsWith($publicPath, ['http://', 'https://'])) {
            return $publicPath;
        }

        return url($publicPath);
    }

    private function handleOfflineException(Throwable $e, string $fallbackMessage)
    {
        if ($e instanceof AuthorizationException) {
            return self::error('You are not allowed to perform this offline action.', 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return self::error('Offline package resource was not found.', 404);
        }

        if ($e instanceof QueryException) {
            return self::error('Offline data could not be saved due to a database conflict.', 409, [
                'reason' => 'database_conflict',
            ]);
        }

        if ($e instanceof RuntimeException) {
            [$message, $status, $reason] = $this->mapOfflineRuntimeException($e);

            return self::error($message, $status, [
                'reason' => $reason,
            ]);
        }

        return self::error($fallbackMessage, 500, [
            'reason' => 'unexpected_error',
        ]);
    }

    private function mapOfflineRuntimeException(RuntimeException $e): array
    {
        return match ($e->getMessage()) {
            'Invalid download token.' => ['Invalid download token.', 422, 'invalid_token'],
            'Download token is revoked.' => ['Download token has been revoked.', 410, 'revoked_token'],
            'Download token has expired.' => ['Download token has expired.', 410, 'expired_token'],
            'Token is restricted to another device.' => ['Download token is restricted to another device.', 409, 'device_mismatch'],
            default => [$e->getMessage() !== '' ? $e->getMessage() : 'Offline operation failed.', 422, 'offline_runtime_error'],
        };
    }
}
