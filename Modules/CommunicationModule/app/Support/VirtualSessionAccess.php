<?php

namespace Modules\CommunicationModule\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use LogicException;
use Modules\CommunicationModule\Models\VirtualSession;
use Modules\LearningModule\Models\Enrollment;

class VirtualSessionAccess
{
    public static function canManage(AuthenticatableUser $user, VirtualSession $session): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return (int) $session->host_id === (int) $user->id;
    }

    public static function canJoin(AuthenticatableUser $user, VirtualSession $session): bool
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

    public static function canCreateForCourse(AuthenticatableUser $user, ?int $courseId): bool
    {
        if ($courseId === null || $user->hasRole('super-admin')) {
            return true;
        }

        return self::instructorCoursesRelation($user)
            ->where('courses.course_id', $courseId)
            ->exists();
    }

    protected static function instructorCoursesRelation(AuthenticatableUser $user): BelongsToMany
    {
        if (method_exists($user, 'instructorCourses')) {
            return $user->instructorCourses();
        }

        if (method_exists($user, 'instructedCourses')) {
            return $user->instructedCourses();
        }

        throw new LogicException('Authenticated user model does not expose an instructor courses relation.');
    }
}
