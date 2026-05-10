<?php

namespace Modules\CommunicationModule\Observers;

use Modules\CommunicationModule\Services\V1\NotificationService;
use Spatie\Activitylog\Models\Activity;

class ActivityLogObserver
{
    public function created(Activity $activity): void
    {
        app(NotificationService::class)->sendActivityLogNotification($activity);
    }
}
