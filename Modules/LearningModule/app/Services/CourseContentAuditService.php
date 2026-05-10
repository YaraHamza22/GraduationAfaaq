<?php

namespace Modules\LearningModule\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\CommunicationModule\Services\V1\NotificationService;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\CourseContentAudit;
use Modules\UserMangementModule\Models\User;

class CourseContentAuditService
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function listForCourse(Course $course, int $perPage = 15): LengthAwarePaginator
    {
        return CourseContentAudit::query()
            ->where('course_id', $course->course_id)
            ->with(['auditor:id,name,email', 'lesson:lesson_id,unit_id,lesson_order'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{verdict: string, notes?: string|null, lesson_id?: int|null}  $data
     */
    public function submit(Course $course, array $data): CourseContentAudit
    {
        /** @var User $auditor */
        $auditor = Auth::user();

        return DB::transaction(function () use ($course, $data, $auditor) {
            $audit = CourseContentAudit::query()->create([
                'course_id' => $course->course_id,
                'user_id' => $auditor->id,
                'lesson_id' => $data['lesson_id'] ?? null,
                'verdict' => $data['verdict'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->notifyStakeholders($course, $audit, $auditor);

            return $audit->load(['auditor:id,name,email', 'lesson:lesson_id,unit_id,lesson_order']);
        });
    }

    private function notifyStakeholders(Course $course, CourseContentAudit $audit, User $auditor): void
    {
        $recipientIds = collect([$course->created_by])
            ->merge($course->instructors()->pluck('id'))
            ->filter()
            ->unique()
            ->reject(fn (int|string|null $id) => (int) $id === (int) $auditor->id)
            ->values()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        $courseTitle = $course->getTranslation('title', app()->getLocale())
            ?: $course->getTranslation('title', 'en')
            ?: __('Course');

        $verdictLabel = match ($audit->verdict) {
            'approved' => __('مراجعة: مقبول'),
            'changes_requested' => __('مراجعة: مطلوب تعديل'),
            'follow_up' => __('مراجعة: يحتاج متابعة'),
            default => $audit->verdict,
        };

        $body = trim(($audit->notes ?: '')."\n\n".$verdictLabel);

        $this->notificationService->sendToUsers([
            'user_ids' => $recipientIds,
            'title' => __('إشعار مراجعة محتوى').': '.$courseTitle,
            'body' => $body !== '' ? $body : $verdictLabel,
            'type' => 'course_content_audit',
            'data' => [
                'course_id' => $course->course_id,
                'course_slug' => $course->slug,
                'audit_id' => $audit->id,
                'verdict' => $audit->verdict,
                'lesson_id' => $audit->lesson_id,
                'auditor_id' => $auditor->id,
            ],
        ]);

        // Always notify super admins when an auditor submits a review.
        $this->notificationService->sendToUsers([
            'role_names' => ['super-admin'],
            'title' => __('إشعار مراجعة محتوى').': '.$courseTitle,
            'body' => $body !== '' ? $body : $verdictLabel,
            'type' => 'course_content_audit.super_admin',
            'data' => [
                'course_id' => $course->course_id,
                'course_slug' => $course->slug,
                'audit_id' => $audit->id,
                'verdict' => $audit->verdict,
                'lesson_id' => $audit->lesson_id,
                'auditor_id' => $auditor->id,
                'source' => 'auditor_review',
            ],
        ]);
    }
}
