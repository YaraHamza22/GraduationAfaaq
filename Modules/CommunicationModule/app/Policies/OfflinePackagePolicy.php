<?php

namespace Modules\CommunicationModule\Policies;

use App\Models\User;
use Modules\CommunicationModule\Models\OfflinePackage;
use Modules\LearningModule\Models\CourseInstructor;
use Modules\LearningModule\Models\Enrollment;

class OfflinePackagePolicy
{
    public function update(User $user, OfflinePackage $offlinePackage): bool
    {
        return (int) $offlinePackage->created_by === (int) $user->id;
    }

    public function download(User $user, OfflinePackage $offlinePackage): bool
    {
        if (! $offlinePackage->is_active) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])
            || (int) $offlinePackage->created_by === (int) $user->id) {
            return true;
        }

        $isAssignedInstructor = CourseInstructor::query()
            ->where('course_id', $offlinePackage->course_id)
            ->where('instructor_id', $user->id)
            ->exists();

        if ($isAssignedInstructor) {
            return true;
        }

        return Enrollment::query()
            ->where('learner_id', $user->id)
            ->where('course_id', $offlinePackage->course_id)
            ->whereIn('enrollment_status', ['active', 'completed'])
            ->exists();
    }
}
