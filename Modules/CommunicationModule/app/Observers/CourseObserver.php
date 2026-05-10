<?php

namespace Modules\CommunicationModule\Observers;

use Modules\CommunicationModule\Services\V1\NotificationService;
use Modules\LearningModule\Enums\CourseStatus;
use Modules\LearningModule\Models\Course;

class CourseObserver
{
    public function updated(Course $course): void
    {
        $publishedNow = $course->wasChanged('status') && (string) $course->status === CourseStatus::PUBLISHED->value;
        $publishedAtSetNow = $course->wasChanged('published_at') && $course->published_at !== null;

        if (! $publishedNow && ! $publishedAtSetNow) {
            return;
        }

        app(NotificationService::class)->sendNewCourseContentNotification($course);
    }
}
