<?php

use Illuminate\Support\Facades\Route;
use Modules\LearningModule\Http\Controllers\CourseController;
use Modules\LearningModule\Http\Controllers\LessonController;
use Modules\LearningModule\Http\Controllers\UnitController;
use Modules\AssesmentModule\Http\Controllers\Api\V1\AssessmentProgressController;
use Modules\ReportingModule\Http\Controllers\TeacherDashboardController;
use Modules\UserMangementModule\Http\Controllers\Api\V1\InstructorStudentController;

/**
 |----------------------------------------------------
 | Instructor Dashboard Routes
 | ---------------------------------------------------
 * Routes for instructors to manage their assigned courses
 * Security:
 * 1. JWT Auth (Identity)
 * 2. Instructor Role (Global/Org Role)
 * 3. CourseAccessScope: Filters queries to only show courses assigned to this user.
 * @prefix    api/v1
 * @auth   Required (JWT)
 * @access Instructor Only
 * @scope  CourseAccessScope (filters courses by instructor to insure instructors can only access their assigned courses)
 */
Route::group(['middleware' => ['auth:api', 'role:instructor,api']], function () {

    /**
    |--------------------------------------------------------------------------
    | Instructor Dashboard (Reporting Module)
    |--------------------------------------------------------------------------
     */
    /**
     * @name   Instructor Dashboard (self)
     * @path   GET /api/v1/instructor/dashboard
     * @desc   Retrieve dashboard data for the authenticated instructor (assigned courses, metrics, etc.).
     * @note   Distinct from GET /api/v1/instructor/dashboard/{instructorId} (ReportingModule) and avoids clashing with student GET /api/v1/student/dashboard.
     * @controller TeacherDashboardController@dashboard
     */
    Route::get('/instructor/dashboard', [TeacherDashboardController::class, 'dashboard']);
    Route::get('/instructor/students', [InstructorStudentController::class, 'index']);

    /**
    |--------------------------------------------------------------------------
    | 1. Assigned Course Overview (Learning Module)
    |--------------------------------------------------------------------------
     */

    /**
     * @name   List Instructor Courses
     * @path   GET /api/v1/my-courses
     * @desc   Retrieve all courses where the authenticated user is the assigned instructor.
     * @controller CourseController@index
     */
    Route::get('/my-courses', [CourseController::class, 'index']);

    /**
     * @name   View Course details
     * @path   GET /api/v1/my-courses/{course}
     * @desc   Retrieve full details for a specific assigned course.
     * @controller CourseController@show
     */
    Route::get('/my-courses/{course}', [CourseController::class, 'show']);

    /**
     * @name   Get Course Duration
     * @path   GET /api/v1/my-courses/{course}/duration
     * @desc   Get total duration of an assigned course.
     * @param  {course: slug}
     * @controller CourseController@getDuration
     */
    Route::get('/my-courses/{course}/duration', [CourseController::class, 'getDuration']);

    /**
     * @name   Check Course Publishability
     * @path   GET /api/v1/my-courses/{course}/publishability
     * @desc   Check if course can be published.
     * @param  {course: slug}
     * @controller CourseController@checkPublishability
     */
    Route::get('/my-courses/{course}/publishability', [CourseController::class, 'checkPublishability']);

    /**
    |--------------------------------------------------------------------------
    | 2. Unit Management
    |--------------------------------------------------------------------------
     */

    /**
     * @name   List Course Units
     * @path   GET /api/v1/my-courses/{course}/units
     * @desc   Fetch the units for a specific course.
     * @param {course: slug}
     */
    Route::get('/my-courses/{course}/units', [UnitController::class, 'byCourse']);//policy?

    /**
     * @name   Reorder Units in Course
     * @path   POST /api/v1/my-courses/{course}/units/reorder
     * @desc   Reorder units within an assigned course.
     * @param  {course: slug}
     * @controller UnitController@reorder
     */
    Route::post('/my-courses/{course}/units/reorder', [UnitController::class, 'reorder']);

    /**
     * @name   Get Unit Count
     * @path   GET /api/v1/my-courses/{course}/units/count
     * @desc   Get number of units in an assigned course.
     * @param  {course: slug}
     * @controller UnitController@getUnitCount
     */
    Route::get('/my-courses/{course}/units/count', [UnitController::class, 'getUnitCount']);

    /**
     * @name   Add Course Unit
     * @path   POST /api/v1/my-courses/{course}/units
     * @desc   Create a new unit within an instructor's course.
     */
    Route::post('/my-courses/{course}/units', [UnitController::class, 'storeForCourse']);

    /**
     * @name   View Unit
     * @path   GET /api/v1/my-courses/{course}/units/{unit}
     * @desc   view details of a specific unit.
     */
    Route::get('/my-courses/{course}/units/{unit}', [UnitController::class, 'showForCourse']);

    /**
     * @name   Update Course Unit
     * @path   PUT /api/v1/my-courses/{course}/units/{unit}
     * @desc   Modify unit attributes by course instructor.
     */
    Route::put('/my-courses/{course}/units/{unit}', [UnitController::class, 'updateForCourse']);//policy

    /**
     * @name   Delete Course Unit
     * @path   DELETE /api/v1/my-courses/{course}/units/{unit}
     * @desc   Soft Deletes a module and its associated lessons.
     */
    Route::delete('/my-courses/{course}/units/{unit}', [UnitController::class, 'destroyForCourse']);

    /**
     * @name   Get Unit Duration
     * @path   GET /api/v1/my-courses/{course}/units/{unit}/duration
     * @desc   Get duration of a unit.
     * @param  {course: slug, unit: slug}
     * @controller UnitController@getDurationForCourse
     */
    Route::get('/my-courses/{course}/units/{unit}/duration', [UnitController::class, 'getDurationForCourse']);

    /**
     * @name   Move Unit to Position
     * @path   PUT /api/v1/my-courses/{course}/units/{unit}/position
     * @desc   Change unit order position within the course.
     * @param  {course: slug, unit: slug}
     * @controller UnitController@moveToPositionForCourse
     */
    Route::put('/my-courses/{course}/units/{unit}/position', [UnitController::class, 'moveToPositionForCourse']);

    /**
    |--------------------------------------------------------------------------
    | 3. Lesson Management (Learning Module)
    |--------------------------------------------------------------------------
     */

    /**
     * @name   List Unit Lessons
     * @path   GET /api/v1/my-courses/{course}/units/{unit}/lessons
     * @desc   List all learning materials in a specific unit within instructor's requested course
     */
    Route::get('/my-courses/{course}/units/{unit}/lessons', [LessonController::class, 'indexForCourseUnit']);

    /**
     * @name   Reorder Lessons in Unit
     * @path   POST /api/v1/my-courses/{course}/units/{unit}/lessons/reorder
     * @desc   Reorder lessons within a unit.
     * @param  {course: slug, unit: slug}
     * @controller LessonController@reorderForCourseUnit
     */
    Route::post('/my-courses/{course}/units/{unit}/lessons/reorder', [LessonController::class, 'reorderForCourseUnit']);

    /**
     * @name   Get Lesson Count
     * @path   GET /api/v1/my-courses/{course}/units/{unit}/lessons/count
     * @desc   Get number of lessons in a unit.
     * @param  {course: slug, unit: slug}
     * @controller LessonController@getLessonCountForCourseUnit
     */
    Route::get('/my-courses/{course}/units/{unit}/lessons/count', [LessonController::class, 'getLessonCountForCourseUnit']);

    /**
     * @name   Create Lesson
     * @path   POST /api/v1/my-courses/{course}/units/{unit}/lessons
     * @desc   Add a new lesson
     */
    Route::post('/my-courses/{course}/units/{unit}/lessons', [LessonController::class, 'storeForCourseUnit']);

    /**
     * @name   View Lesson
     * @path   GET /api/v1/my-courses/{course}/units/{unit}/lessons/{lesson}
     * @desc   Retrieve lesson content for review or editing.
     */
    Route::get('/my-courses/{course}/units/{unit}/lessons/{lesson}', [LessonController::class, 'showForCourseUnit']);

    /**
     * @name   Update Lesson
     * @path   PUT /api/v1/my-courses/{course}/units/{unit}/lessons/{lesson}
     * @desc   Modify lesson content
     */
    Route::put('/my-courses/{course}/units/{unit}/lessons/{lesson}', [LessonController::class, 'updateForCourseUnit']);

    /**
     * @name   Delete Lesson
     * @path   DELETE /api/v1/my-courses/{course}/units/{unit}/lessons/{lesson}
     * @desc   Soft Deletes lesson content.
     */
    Route::delete('/my-courses/{course}/units/{unit}/lessons/{lesson}', [LessonController::class, 'destroyForCourseUnit']);

    /**
     * @name   Get Lesson Duration
     * @path   GET /api/v1/my-courses/{course}/units/{unit}/lessons/{lesson}/duration
     * @desc   Get duration of a lesson.
     * @param  {course: slug, unit: slug, lesson: slug}
     * @controller LessonController@getDurationForCourseUnit
     */
    Route::get('/my-courses/{course}/units/{unit}/lessons/{lesson}/duration', [LessonController::class, 'getDurationForCourseUnit']);

    /**
     * @name   Move Lesson to Position
     * @path   PUT /api/v1/my-courses/{course}/units/{unit}/lessons/{lesson}/position
     * @desc   Change lesson order position within the unit.
     * @param  {course: slug, unit: slug, lesson: slug}
     * @controller LessonController@moveToPositionForCourseUnit
     */
    Route::put('/my-courses/{course}/units/{unit}/lessons/{lesson}/position', [LessonController::class, 'moveToPositionForCourseUnit']);

    /**
     * @name   Course Assessment Progress Report
     * @path   GET /api/v1/my-courses/{courseId}/assessment-progress
     * @desc   Instructor course-level assessment performance report.
     * @controller AssessmentProgressController@courseProgress
     */
    Route::get('/my-courses/{courseId}/assessment-progress', [AssessmentProgressController::class, 'courseProgress']);

    /*
     * Flat Learning-module URLs (GET /api/v1/units/..., /api/v1/lessons/...) are registered in
     * Modules/LearningModule/routes/api.php. Duplicating them here with role:instructor overwrote
     * permission-only routes and broke clients using other roles (e.g. auditor).
     */
});
