<?php

namespace Modules\CommunicationModule\Policies;

use Modules\CommunicationModule\Models\OfflinePackage;
use Modules\LearningModule\Models\CourseInstructor;
use Modules\LearningModule\Models\Enrollment;

class OfflinePackagePolicy
{
    public function update($user, OfflinePackage $offlinePackage): bool
    {
        return $this->userId($user) > 0
            && (int) $offlinePackage->created_by === $this->userId($user);
    }

    public function download($user, OfflinePackage $offlinePackage): bool
    {
        $userId = $this->userId($user);
        if ($userId <= 0) {
            return false;
        }

        if (! $offlinePackage->is_active) {
            return false;
        }

        if ($this->userHasAnyRole($user, ['super-admin', 'admin'])
            || (int) $offlinePackage->created_by === $userId) {
            return true;
        }

        $isAssignedInstructor = CourseInstructor::query()
            ->where('course_id', $offlinePackage->course_id)
            ->where('instructor_id', $userId)
            ->exists();

        if ($isAssignedInstructor) {
            return true;
        }

        return Enrollment::query()
            ->where('learner_id', $userId)
            ->where('course_id', $offlinePackage->course_id)
            ->whereIn('enrollment_status', ['active', 'completed'])
            ->exists();
    }

    private function userId($user): int
    {
        if (! is_object($user) || ! isset($user->id)) {
            return 0;
        }

        return (int) $user->id;
    }

    private function userHasAnyRole($user, array $roles): bool
    {
        return is_object($user)
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($roles);
    }
}
