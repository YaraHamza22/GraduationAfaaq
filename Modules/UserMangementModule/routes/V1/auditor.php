<?php

use Illuminate\Support\Facades\Route;
use Modules\CommunicationModule\Http\Controllers\Api\V1\NotificationController;
use Modules\LearningModule\Http\Controllers\Api\V1\Auditor\CourseContentReviewController;
use Modules\LearningModule\Http\Controllers\CourseController;
use Modules\LearningModule\Http\Controllers\LessonController;
use Modules\LearningModule\Http\Controllers\UnitController;
use Modules\AssesmentModule\Http\Controllers\Api\V1\QuizController;

/**
 |------------------------------------------------------------------------------
 | Auditor routes 
 |------------------------------------------------------------------------------
 */

Route::group(['middleware' => ['auth:api', 'role:auditor,api']], function () {

    Route::get('/auditor/courses', [CourseController::class, 'index'])->middleware('permission:list-courses');
    Route::get('/auditor/courses/{course}', [CourseController::class, 'show'])->middleware('permission:show-course');

    Route::get('/auditor/courses/{course}/units', [UnitController::class, 'byCourse'])->middleware('permission:list-units');
    Route::get('/auditor/courses/{course}/units/{unit}', [UnitController::class, 'showForCourse'])->middleware('permission:show-unit');

    Route::get('/auditor/courses/{course}/units/{unit}/lessons', [LessonController::class, 'indexForCourseUnit'])->middleware('permission:list-lessons');
    Route::get('/auditor/courses/{course}/units/{unit}/lessons/{lesson}', [LessonController::class, 'showForCourseUnit'])->middleware('permission:show-lesson');

    Route::get('/auditor/quizzes', [QuizController::class, 'index'])->middleware('permission:list-quiz');
    Route::get('/auditor/quizzes/{quiz}', [QuizController::class, 'show'])->middleware('permission:show-quiz');

    Route::get('/auditor/courses/{course}/content-reviews', [CourseContentReviewController::class, 'index'])->middleware('permission:list-reviews');
    Route::post('/auditor/courses/{course}/content-reviews', [CourseContentReviewController::class, 'store'])->middleware('permission:create-review');

    Route::get('/auditor/notifications', [NotificationController::class, 'index'])->middleware('permission:create-notification');
    Route::get('/auditor/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('permission:create-notification');
    Route::post('/auditor/notifications/{notificationId}/read', [NotificationController::class, 'markRead'])->middleware('permission:create-notification');
    Route::post('/auditor/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('permission:create-notification');
});
