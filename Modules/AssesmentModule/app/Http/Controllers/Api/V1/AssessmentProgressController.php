<?php

namespace Modules\AssesmentModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\AssesmentModule\Services\V1\CertificateEligibilityService;
use Modules\AssesmentModule\Services\V1\CourseQuizProgressService;
use Throwable;

class AssessmentProgressController extends Controller
{
    public function __construct(
        private CourseQuizProgressService $progressService,
        private CertificateEligibilityService $eligibilityService
    ) {
    }

    public function courseProgress(Request $request, $courseId)
    {
        try {
            if (!is_numeric($courseId)) {
                return self::error('Invalid course id. It must be numeric.', 422);
            }

            $courseId = (int) $courseId;
            $studentId = (int) ($request->input('student_id') ?: Auth::id());
            $progress = $this->progressService->build($courseId, $studentId);
            $certificate = $this->eligibilityService->evaluateAndIssue($courseId, $studentId, $progress);

            return self::success([
                'progress' => $progress,
                'certificate' => $certificate,
            ], 'Assessment progress fetched successfully.');
        } catch (Throwable $e) {
            return self::error('Failed to fetch assessment progress.', 500, $e->getMessage());
        }
    }

    public function courseQuizAvailability(Request $request, $courseId)
    {
        try {
            if (!is_numeric($courseId)) {
                return self::error('Invalid course id. It must be numeric.', 422);
            }

            $courseId = (int) $courseId;
            $studentId = (int) ($request->input('student_id') ?: Auth::id());
            $availability = $this->progressService->courseQuizAvailabilityForStudent($courseId, $studentId);

            return self::success($availability, 'Course quiz availability fetched successfully.');
        } catch (Throwable $e) {
            return self::error('Failed to fetch course quiz availability.', 500, $e->getMessage());
        }
    }
}
