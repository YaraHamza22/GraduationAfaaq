<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class SecurityAuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:super-admin,api');
    }

    public function index(Request $request)
    {
        $query = Activity::query()
            ->select(['id', 'log_name', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'event', 'created_at'])
            ->with(['causer:id,name,email'])
            ->latest('id');

        if ($request->filled('event')) {
            $query->where('event', (string) $request->query('event'));
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', (string) $request->query('log_name'));
        }

        return self::paginated($query->paginate(25), 'Security audit logs fetched successfully.');
    }
}
