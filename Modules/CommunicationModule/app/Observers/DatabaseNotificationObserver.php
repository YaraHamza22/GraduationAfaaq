<?php

namespace Modules\CommunicationModule\Observers;

use Illuminate\Notifications\DatabaseNotification;
use Modules\CommunicationModule\Events\InAppNotificationCreated;
use Modules\CommunicationModule\Events\InAppNotificationRead;
use Modules\UserMangementModule\Models\User;

class DatabaseNotificationObserver
{
    public function created(DatabaseNotification $notification): void
    {
        if ($notification->notifiable_type !== User::class) {
            return;
        }

        event(new InAppNotificationCreated($notification));
    }

    public function updated(DatabaseNotification $notification): void
    {
        if ($notification->notifiable_type !== User::class) {
            return;
        }

        if ($notification->wasChanged('read_at') && $notification->read_at !== null) {
            event(new InAppNotificationRead($notification));
        }
    }
}
