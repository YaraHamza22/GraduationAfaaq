<?php

namespace Modules\CommunicationModule\Policies;

use Modules\UserMangementModule\Models\User;
use Modules\CommunicationModule\Models\OfflinePackage;

class OfflinePackagePolicy
{
    public function update(User $user, OfflinePackage $offlinePackage): bool
    {
        return (int) $offlinePackage->created_by === (int) $user->id;
    }
}
