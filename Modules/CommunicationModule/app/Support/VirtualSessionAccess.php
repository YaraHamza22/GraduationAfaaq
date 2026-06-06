<?php

namespace Modules\CommunicationModule\Support;

use App\Models\User;
use Modules\CommunicationModule\Models\VirtualSession;
use Modules\LearningModule\Models\Enrollment;

class VirtualSessionAccess
{
    public static function canManage(User $user, VirtualSession $session): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return (int) $session->host_id === (int) $user->id;
    }

    public static function canJoin(User $user, VirtualSession $session): bool
    {
        if (self::canManage($user, $session)) {
            return true;
        }

        if (! $session->course_id) {
            return false;
        }

        return Enrollment::query()
            ->where('learner_id', $user->id)
            ->where('course_id', $session->course_id)
            ->whereIn('enrollment_status', ['active', 'completed'])
            ->exists();
    }

    public static function canCreateForCourse(User $user, ?int $courseId): bool
    {
        if ($courseId === null || $user->hasRole('super-admin')) {
            return true;
        }

        return $user->instructorCourses()
            ->where('courses.course_id', $courseId)
            ->exists();
    }
}
