<?php

namespace Modules\ReportingModule\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Backward-compatible alias for the old teacher controller name.
 *
 * {@see InstructorDashboardController::dashboard()} requires an instructor id from the URL.
 * The UserManagement instructor route {@code GET /api/v1/instructor/dashboard} has no segment,
 * so this subclass resolves the authenticated user's id when the parameter is omitted.
 */
class TeacherDashboardController extends InstructorDashboardController
{
    public function dashboard(?int $instructorId = null): JsonResponse
    {
        $id = $instructorId ?? (int) auth()->id();

        if ($id < 1) {
            return $this->error('Unable to resolve instructor.', 400);
        }

        return parent::dashboard($id);
    }
}
