<?php

namespace Modules\CommunicationModule\Services\V1;

use Illuminate\Support\Collection;
use Modules\UserMangementModule\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Modules\CommunicationModule\Models\ChatMessage;
use Modules\CommunicationModule\Models\VirtualSession;
use Modules\AssesmentModule\Models\Attempt;
use Modules\AssesmentModule\Models\CourseCertificate;
use Modules\LearningModule\Models\Course;
use Spatie\Activitylog\Models\Activity;

class NotificationService
{
    public function sendToUsers(array $payload): int
    {
        $targetUsers = $this->resolveTargetUsers($payload);
        $count = 0;

        foreach ($targetUsers as $user) {
            $user->notify(new GenericDatabaseNotification(
                $payload['title'],
                $payload['body'],
                $payload['type'] ?? 'system',
                $payload['data'] ?? []
            ));
            $count++;
        }

        return $count;
    }

    private function resolveTargetUsers(array $payload): Collection
    {
        $query = User::query();

        $allUsers = (bool) ($payload['all_users'] ?? false);
        $hasUserIds = !empty($payload['user_ids']);
        $hasRoles = !empty($payload['role_names']);

        if (!$allUsers && !$hasUserIds && !$hasRoles) {
            return collect();
        }

        if (!$allUsers) {
            $query->where(function ($q) use ($payload, $hasUserIds, $hasRoles) {
                if ($hasUserIds) {
                    $q->whereIn('id', $payload['user_ids']);
                }

                if ($hasRoles) {
                    $q->orWhereHas('roles', function ($roleQuery) use ($payload) {
                        $roleQuery->whereIn('name', $payload['role_names']);
                    });
                }
            });
        }

        return $query->get()->unique('id')->values();
    }

    public function markAllReadForUser(int $userId): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function sendChatMessageNotification(int $recipientId, ChatMessage $message): bool
    {
        $recipient = User::query()->find($recipientId);

        if (!$recipient) {
            return false;
        }

        $recipient->notify(new GenericDatabaseNotification(
            'New message',
            $message->body,
            'chat.message',
            [
                'chat_thread_id' => $message->chat_thread_id,
                'chat_message_id' => $message->id,
                'sender_id' => $message->sender_id,
            ]
        ));

        return true;
    }

    public function sendActivityLogNotification(Activity $activity): int
    {
        return $this->sendToUsers([
            'role_names' => ['super-admin'],
            'title' => 'New activity log recorded',
            'body' => (string) ($activity->description ?: 'A new activity has been logged.'),
            'type' => 'activity.log',
            'data' => [
                'activity_id' => $activity->id,
                'log_name' => $activity->log_name,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_id' => $activity->causer_id,
                'created_at' => optional($activity->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function sendVirtualSessionNotification(VirtualSession $session, string $status): bool
    {
        if (! $session->host_id) {
            return false;
        }

        return $this->sendToUsers([
            'user_ids' => [(int) $session->host_id],
            'title' => 'Virtual session update',
            'body' => sprintf(
                '%s session "%s" is now %s.',
                strtoupper((string) $session->provider),
                (string) $session->title,
                $status
            ),
            'type' => 'virtual_session.' . $status,
            'data' => [
                'virtual_session_id' => $session->id,
                'provider' => $session->provider,
                'status' => $session->status,
                'join_url' => $session->join_url,
                'starts_at' => optional($session->starts_at)->toIso8601String(),
                'ends_at' => optional($session->ends_at)->toIso8601String(),
            ],
        ]) > 0;
    }

    public function sendAssessmentResultNotification(Attempt $attempt): bool
    {
        if (! $attempt->student_id) {
            return false;
        }

        return $this->sendToUsers([
            'user_ids' => [(int) $attempt->student_id],
            'title' => 'Assessment result published',
            'body' => sprintf(
                'Your assessment attempt has been graded. Score: %s. Result: %s.',
                $attempt->score ?? 0,
                $attempt->is_passed ? 'Passed' : 'Not passed'
            ),
            'type' => 'assessment.result',
            'data' => [
                'attempt_id' => $attempt->id,
                'quiz_id' => $attempt->quiz_id,
                'score' => $attempt->score,
                'is_passed' => (bool) $attempt->is_passed,
                'graded_at' => optional($attempt->graded_at)->toIso8601String(),
            ],
        ]) > 0;
    }

    public function sendCertificateIssuedNotification(CourseCertificate $certificate): bool
    {
        if (! $certificate->student_id) {
            return false;
        }

        return $this->sendToUsers([
            'user_ids' => [(int) $certificate->student_id],
            'title' => 'Course certificate issued',
            'body' => 'Congratulations! Your course certificate is now available for download.',
            'type' => 'certificate.issued',
            'data' => [
                'certificate_id' => $certificate->id,
                'course_id' => $certificate->course_id,
                'issued_at' => optional($certificate->issued_at)->toIso8601String(),
                'weighted_percentage' => $certificate->weighted_percentage,
            ],
        ]) > 0;
    }

    public function sendNewCourseContentNotification(Course $course): int
    {
        $course->loadMissing(['enrollments:learner_id,course_id']);
        $learnerIds = $course->enrollments
            ->pluck('learner_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($learnerIds)) {
            return 0;
        }

        return $this->sendToUsers([
            'user_ids' => $learnerIds,
            'title' => 'New course content available',
            'body' => 'New content was added to one of your enrolled courses.',
            'type' => 'course.new_content',
            'data' => [
                'course_id' => $course->course_id,
                'course_slug' => $course->slug,
                'published_at' => optional($course->published_at)->toIso8601String(),
            ],
        ]);
    }
}
