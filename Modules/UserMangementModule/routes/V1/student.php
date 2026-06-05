<?php

use Illuminate\Support\Facades\Route;
use Modules\LearningModule\Http\Controllers\CourseController;
use Modules\LearningModule\Http\Controllers\EnrollmentController;
use Modules\LearningModule\Http\Controllers\LessonController;
use Modules\LearningModule\Http\Controllers\UnitController;
use Modules\AssesmentModule\Http\Controllers\Api\V1\CertificateController;
use Modules\ReportingModule\Http\Controllers\StudentDashboardController;
use Modules\UserMangementModule\Http\Controllers\Api\V1\StudentController;
use Modules\UserMangementModule\Http\Controllers\Api\V1\StudentInstructorDirectoryController;

/**
 |----------------------------------------------------
 | Student Dashboard Routes
 | ---------------------------------------------------
 * Routes for learners to access their enrolled content.
 * Security:
 * 1. JWT Auth
 * 2. Student Role
 * 3. CourseAccessScope: Filters all queries to the 'enrollment' table.
 * @prefix api/v1
 * @auth   Required (JWT)
 * @access Student (most routes); instructor directory also allows super-admin for oversight.
 * @scope  CourseAccessScope (filters courses by student to insure students can only access their enrolled courses)
 */
Route::middleware(['auth:api', 'role:student|super-admin,api'])->group(function () {
    /**
     * @name   Instructor directory | دليل المدرّسين (طالب أو مشرف عام)
     * @path   GET /api/v1/student/instructors
     * @controller StudentInstructorDirectoryController@index
     */
    Route::get('/student/instructors', [StudentInstructorDirectoryController::class, 'index']);

    /**
     * @name   Instructor public profile
     * @path   GET /api/v1/student/instructors/{instructor}
     * @controller StudentInstructorDirectoryController@show
     */
    Route::get('/student/instructors/{instructor}', [StudentInstructorDirectoryController::class, 'show'])
        ->whereNumber('instructor');
});

Route::group(['middleware' => ['auth:api', 'role:student,api']], function () {
    /**
    |--------------------------------------------------------------------------
    | Student Dashboard (Reporting Module)
    |--------------------------------------------------------------------------
     */
    /**
     * @name   Student Dashboard (self)
     * @path   GET /api/v1/student/dashboard
     * @desc   Retrieve dashboard data for the authenticated student (progress, enrolled courses, etc.).
     * @note   Distinct from GET /api/v1/student/dashboard/{studentId} (ReportingModule); avoids route clash with instructor dashboard.
     * @controller StudentDashboardController@dashboard
     */
    Route::get('/student/dashboard', [StudentDashboardController::class, 'dashboard']);

    /**
     * @name   My Courses
     * @path   GET /api/v1/me/with-courses
     * @desc   Authenticated student: account + profile and enrolled courses only.
     * @controller StudentController@meWithCourses
     */
    Route::get('/me/with-courses', [StudentController::class, 'meWithCourses']);

    /**
     * @name   My Profile With Quizzes And Courses
     * @path   GET /api/v1/me/with-quizzes
     * @desc   Authenticated student: account + profile, quizzes (assigned / attempted), enrolled courses.
     * @controller StudentController@meWithQuizzes
     */
    Route::get('/me/with-quizzes', [StudentController::class, 'meWithQuizzes']);

    /**
    |--------------------------------------------------------------------------
    | Course Discovery & Enrollment (Learning Module)
    |--------------------------------------------------------------------------
     */
    /**
     * @name   List Enrollable Courses
     * @path   GET /api/v1/courses/enrollable/list
     * @desc   List courses available for the student to enroll in.
     * @controller CourseController@enrollable
     */
    Route::get('/courses/enrollable/list', [CourseController::class, 'enrollable']);

    /**
     * @name   Enroll in Course
     * @path   POST /api/v1/enrollments
     * @desc   Enroll the authenticated student in a course. Body: course_id, enrollment_type (optional).
     * @body   {course_id: int, enrollment_type?: string}
     * @controller EnrollmentController@store
     */
    Route::post('/enrollments', [EnrollmentController::class, 'store']);

    /**
     * @name   My Enrollments
     * @path   GET /api/v1/enrollments
     * @desc   List enrollments for the authenticated student (filtered by learner_id).
     * @controller EnrollmentController@index
     */
    Route::get('/enrollments', [EnrollmentController::class, 'index']);

    /**
     * @name   View My Enrollment
     * @path   GET /api/v1/enrollments/{enrollment}
     * @desc   View details of one of the student's enrollments.
     * @controller EnrollmentController@show
     */
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);

    /**
     * @name   My Enrollment Progress
     * @path   GET /api/v1/enrollments/{enrollment}/progress
     * @desc   Get progress details for an enrollment (units/lessons completed, percentage).
     * @controller EnrollmentController@getProgress
     */
    Route::get('/enrollments/{enrollment}/progress', [EnrollmentController::class, 'getProgress']);

    /**
     * @name   Complete Lesson
     * @path   POST /api/v1/enrollments/{enrollment}/lessons/{lesson}/complete
     * @desc   Mark a lesson as completed for the authenticated student's enrollment.
     * @controller EnrollmentController@completeLesson
     */
    Route::post('/enrollments/{enrollment}/lessons/{lesson}/complete', [EnrollmentController::class, 'completeLesson']);

    /**
     * @name   My Enrolled Courses
     * @path   GET /api/v1/my-learning
     * @desc   List all courses the student has enrolled in.
     * @controller CourseController@index
     */
    Route::get('/my-learning', [CourseController::class, 'index']);

    /**
     * @name   View Enrolled Course
     * @path   GET /api/v1/my-learning/{course}
     * @desc   View details of a specific enrolled course.
     * @param  {course: slug}
     * @controller CourseController@show
     */
    Route::get('/my-learning/{course}', [CourseController::class, 'show']);

    /**
    |--------------------------------------------------------------------------
    | Units & Lessons (Learning Module)
    |--------------------------------------------------------------------------
     */

    /**
     * @name   List Course Units
     * @path   GET /api/v1/my-learning/{course}/units
     * @desc   List units for an enrolled course.
     * @param  {course: slug}
     * @controller UnitController@byCourse
     */
    Route::get('/my-learning/{course}/units', [UnitController::class, 'byCourse']);

    /**
     * @name   View Unit
     * @path   GET /api/v1/my-learning/{course}/units/{unit}
     * @desc   View a unit within an enrolled course.
     * @param  {course: slug, unit: slug}
     * @controller UnitController@showForCourse
     */
    Route::get('/my-learning/{course}/units/{unit}', [UnitController::class, 'showForCourse']);

    /**
     * @name   Get Unit Duration
     * @path   GET /api/v1/my-learning/{course}/units/{unit}/duration
     * @desc   Get duration of a unit.
     * @param  {course: slug, unit: slug}
     * @controller UnitController@getDurationForCourse
     */
    Route::get('/my-learning/{course}/units/{unit}/duration', [UnitController::class, 'getDurationForCourse']);

    /**
     * @name   List Unit Lessons
     * @path   GET /api/v1/my-learning/{course}/units/{unit}/lessons
     * @desc   List lessons in a unit within an enrolled course.
     * @param  {course: slug, unit: slug}
     * @controller LessonController@indexForCourseUnit
     */
    Route::get('/my-learning/{course}/units/{unit}/lessons', [LessonController::class, 'indexForCourseUnit']);

    /**
     * @name   View Lesson
     * @path   GET /api/v1/my-learning/{course}/units/{unit}/lessons/{lesson}
     * @desc   View a lesson within an enrolled course unit.
     * @param  {course: slug, unit: slug, lesson: slug}
     * @controller LessonController@showForCourseUnit
     */
    Route::get('/my-learning/{course}/units/{unit}/lessons/{lesson}', [LessonController::class, 'showForCourseUnit']);

    /**
     * @name   Get Lesson Duration
     * @path   GET /api/v1/my-learning/{course}/units/{unit}/lessons/{lesson}/duration
     * @desc   Get duration of a lesson.
     * @param  {course: slug, unit: slug, lesson: slug}
     * @controller LessonController@getDurationForCourseUnit
     */
    Route::get('/my-learning/{course}/units/{unit}/lessons/{lesson}/duration', [LessonController::class, 'getDurationForCourseUnit']);

    /**
     * @name   Download Course Certificate
     * @path   GET /api/v1/my-learning/{courseId}/certificate
     * @desc   Download certificate for authenticated student when eligible.
     * @controller CertificateController@download
     */
    Route::get('/my-learning/{courseId}/certificate', [CertificateController::class, 'download']);

    /*
     * Flat Learning-module URLs (GET /api/v1/units/..., /api/v1/lessons/...) live in
     * Modules/LearningModule/routes/api.php with auth:api + permission middleware only.
     * Duplicating them here with role:student overwrote those routes and blocked other roles (e.g. auditor).
     */
});
