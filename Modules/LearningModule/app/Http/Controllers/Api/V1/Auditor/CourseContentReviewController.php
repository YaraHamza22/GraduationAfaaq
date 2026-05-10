<?php

namespace Modules\LearningModule\Http\Controllers\Api\V1\Auditor;

use App\Http\Controllers\Controller;
use Modules\LearningModule\Http\Requests\Auditor\StoreCourseContentReviewRequest;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Services\CourseContentAuditService;

class CourseContentReviewController extends Controller
{
    public function __construct(private CourseContentAuditService $auditService)
    {
        $this->middleware('permission:list-reviews')->only('index');
        $this->middleware('permission:create-review')->only('store');
    }

    public function index(Course $course)
    {
        $perPage = min(max((int) request()->query('per_page', 15), 1), 100);
        $page = $this->auditService->listForCourse($course, $perPage);

        return self::paginated($page, 'تم جلب سجل مراجعات المحتوى.');
    }

    public function store(StoreCourseContentReviewRequest $request, Course $course)
    {
        $audit = $this->auditService->submit($course, $request->validated());

        return self::success([
            'id' => $audit->id,
            'course_id' => $audit->course_id,
            'lesson_id' => $audit->lesson_id,
            'verdict' => $audit->verdict,
            'notes' => $audit->notes,
            'created_at' => $audit->created_at,
            'auditor' => [
                'id' => $audit->auditor?->id,
                'name' => $audit->auditor?->name,
                'email' => $audit->auditor?->email,
            ],
        ], 'تم تسجيل المراجعة وإرسال الإشعارات للمعنيين.', 201);
    }
}
