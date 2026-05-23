<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Modules\ReportingModule\Http\Resources\ActivityLogResource;
use Modules\UserMangementModule\Http\Requests\Api\V1\Security\SecurityAuditLogIndexRequest;
use Spatie\Activitylog\Models\Activity;

/**
 * Sensitive audit trail (السجلات الحساسة) for super-admin: full activity rows with filters.
 */
class SecurityAuditLogController extends Controller
{
    public function index(SecurityAuditLogIndexRequest $request): JsonResponse
    {
        if (!Schema::hasTable('activity_log')) {
            $perPage = (int) ($request->validated()['per_page'] ?? 25);
            $perPage = min(100, max(1, $perPage));
            $paginator = new LengthAwarePaginator([], 0, $perPage, 1);

            return self::paginated($paginator, 'api.security.audit_logs_list_success');
        }

        $filters = $request->validated();

        $query = Activity::query()
            ->with('causer')
            ->orderByDesc('id');

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', 'like', '%'.$filters['subject_type'].'%');
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }

        if (! empty($filters['description'])) {
            $query->where('description', 'like', '%'.$filters['description'].'%');
        }

        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = min(100, max(1, $perPage));

        $paginator = $query->paginate($perPage);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Activity $row) => (new ActivityLogResource($row))->resolve()
            )
        );

        return self::paginated($paginator, 'api.security.audit_logs_list_success');
    }

    public function show(int $activity_log): JsonResponse
    {
        if (!Schema::hasTable('activity_log')) {
            return self::success(null, 'api.security.audit_log_one_success');
        }

        $activity = Activity::query()
            ->with('causer')
            ->findOrFail($activity_log);

        return self::success(
            (new ActivityLogResource($activity))->resolve(),
            'api.security.audit_log_one_success'
        );
    }
}
