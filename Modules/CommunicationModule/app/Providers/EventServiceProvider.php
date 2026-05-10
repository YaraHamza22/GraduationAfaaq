<?php

namespace Modules\CommunicationModule\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\AssesmentModule\Events\AttemptGraded;
use Modules\AssesmentModule\Events\CourseCertificateIssued;
use Modules\CommunicationModule\Events\ChatMessageStored;
use Modules\CommunicationModule\Listeners\SendAssessmentResultInAppNotification;
use Modules\CommunicationModule\Listeners\SendChatMessageInAppNotification;
use Modules\CommunicationModule\Listeners\SendCourseCertificateInAppNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ChatMessageStored::class => [
            SendChatMessageInAppNotification::class,
        ],
        AttemptGraded::class => [
            SendAssessmentResultInAppNotification::class,
        ],
        CourseCertificateIssued::class => [
            SendCourseCertificateInAppNotification::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
