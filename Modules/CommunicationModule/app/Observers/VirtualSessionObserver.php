<?php

namespace Modules\CommunicationModule\Observers;

use Modules\CommunicationModule\Models\VirtualSession;
use Modules\CommunicationModule\Services\V1\NotificationService;

class VirtualSessionObserver
{
    public function created(VirtualSession $virtualSession): void
    {
        if (! $this->supportsNotifications($virtualSession)) {
            return;
        }

        app(NotificationService::class)->sendVirtualSessionNotification($virtualSession, 'created');
    }

    public function updated(VirtualSession $virtualSession): void
    {
        if (! $this->supportsNotifications($virtualSession) || ! $virtualSession->wasChanged('status')) {
            return;
        }

        $status = (string) $virtualSession->status;
        if (! in_array($status, ['published', 'cancelled', 'completed'], true)) {
            return;
        }

        app(NotificationService::class)->sendVirtualSessionNotification($virtualSession, $status);
    }

    private function supportsNotifications(VirtualSession $virtualSession): bool
    {
        return in_array((string) $virtualSession->provider, ['zoom', 'google_meet', 'google_classroom'], true);
    }
}
