<?php

namespace Modules\CommunicationModule\Listeners;

use Modules\AssesmentModule\Events\CourseCertificateIssued;
use Modules\CommunicationModule\Services\V1\NotificationService;

class SendCourseCertificateInAppNotification
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function handle(CourseCertificateIssued $event): void
    {
        $this->notificationService->sendCertificateIssuedNotification($event->certificate);
    }
}
