<?php

namespace Modules\CommunicationModule\Listeners;

use Modules\AssesmentModule\Events\AttemptGraded;
use Modules\CommunicationModule\Services\V1\NotificationService;

class SendAssessmentResultInAppNotification
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function handle(AttemptGraded $event): void
    {
        $this->notificationService->sendAssessmentResultNotification($event->attempt);
        $this->notificationService->sendInstructorGradingCompletedNotification($event->attempt);
    }
}
