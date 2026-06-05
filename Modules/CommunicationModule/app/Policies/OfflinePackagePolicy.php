<?php

namespace Modules\CommunicationModule\Policies;

use Modules\UserMangementModule\Models\User;
use Modules\CommunicationModule\Models\OfflinePackage;
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

        // Admin / super-admin / creator can always download
        if ($user->hasAnyRole(['super-admin', 'admin'])
            || (int) $offlinePackage->created_by === (int) $user->id) {
            return true;
        }

        // Student must be actively enrolled in the course
        return Enrollment::where('learner_id', $user->id)
            ->where('course_id', $offlinePackage->course_id)
            ->where('enrollment_status', 'active')
            ->exists();
    }
}
