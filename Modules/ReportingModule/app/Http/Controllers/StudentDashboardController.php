<?php

namespace Modules\ReportingModule\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\ReportingModule\Services\StudentDashboardService;
use Modules\ReportingModule\Http\Resources\StudentDashboardResource;

/**
 * Controller for Student Dashboard.
 */
class StudentDashboardController extends Controller
{
    protected StudentDashboardService $dashboardService;

    public function __construct(StudentDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function dashboard(?int $studentId = null): JsonResponse
    {
        try {
            $resolvedId = $studentId ?? auth()->id();

            if ($resolvedId === null) {
                return $this->error('Unauthenticated.', 401);
            }

            if ($studentId !== null && (int) $studentId !== (int) auth()->id()) {
                return $this->error('Forbidden.', 403);
            }

            $dashboard = $this->dashboardService->getStudentDashboard((int) $resolvedId);

            return $this->success(
                new StudentDashboardResource($dashboard),
                'Student dashboard retrieved successfully.'
            );
        } catch (Exception $e) {
            return $this->error('Failed to retrieve student dashboard.', 500, $e->getMessage());
        }
    }
}
