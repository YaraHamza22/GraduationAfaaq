<?php

namespace Modules\ReportingModule\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;
use Modules\LearningModule\Enums\EnrollmentStatus;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\Enrollment;
use Modules\UserMangementModule\Models\User;

/**
 * Service for dashboard data aggregation.
 *
 * After deleting organizations and programs, this service is now focused on
 * platform-wide reporting for admins.
 */
class AdminDashboardService
{
    protected CourseAnalyticsService $courseAnalyticsService;
    protected StudentAnalyticsService $studentAnalyticsService;

    public function __construct(
        CourseAnalyticsService $courseAnalyticsService,
        StudentAnalyticsService $studentAnalyticsService
    ) {
        $this->courseAnalyticsService = $courseAnalyticsService;
        $this->studentAnalyticsService = $studentAnalyticsService;
    }

    public function getAdminDashboard(): array
    {
        $cacheKey = 'admin_dashboard';

        return Cache::remember($cacheKey, 300, function () {
            $dashboard = $this->emptyDashboard();

            try {
                $totalStudents = User::whereHas('studentProfile')->count();
                $activeStudents = Enrollment::where('enrollment_status', EnrollmentStatus::ACTIVE)
                    ->distinct('learner_id')
                    ->count('learner_id');

                $totalCourses = Course::count();
                $publishedCourses = Course::whereNotNull('published_at')->count();

                $totalEnrollments = Enrollment::count();
                $completedEnrollments = Enrollment::where('enrollment_status', EnrollmentStatus::COMPLETED)->count();
                $completionRate = $totalEnrollments > 0
                    ? round(($completedEnrollments / $totalEnrollments) * 100, 2)
                    : 0;

                $popularCourses = Course::withCount('enrollments')
                    ->orderBy('enrollments_count', 'desc')
                    ->take(10)
                    ->get()
                    ->map(function ($course) {
                        return [
                            'course_id' => $course->course_id,
                            'title' => $course->title,
                            'enrollments_count' => $course->enrollments_count,
                        ];
                    })->values()->toArray();

                $dashboard['summary'] = [
                    'total_students' => $totalStudents,
                    'active_students' => $activeStudents,
                    'total_courses' => $totalCourses,
                    'published_courses' => $publishedCourses,
                    'total_enrollments' => $totalEnrollments,
                    'completion_rate' => $completionRate,
                ];
                $dashboard['popular_courses'] = $popularCourses;
            } catch (Throwable) {
            }

            try {
                $dashboard['learning_gaps'] = $this->courseAnalyticsService->identifyLearningGaps();
            } catch (Throwable) {
            }

            try {
                $dashboard['course_analytics']['popularity_report'] = $this->courseAnalyticsService->generatePopularityReport([]);
            } catch (Throwable) {
            }

            try {
                $dashboard['student_analytics']['performance_report'] = $this->studentAnalyticsService->generatePerformanceReport([]);
            } catch (Throwable) {
            }

            try {
                $dashboard['student_analytics']['completion_rates'] = $this->studentAnalyticsService->getCompletionRates([]);
            } catch (Throwable) {
            }

            try {
                $dashboard['student_analytics']['learning_time_analysis'] = $this->studentAnalyticsService->getLearningTimeAnalysis([]);
            } catch (Throwable) {
            }

            return $dashboard;
        });
    }

    public function emptyDashboard(): array
    {
        return [
            'summary' => [
                'total_students' => 0,
                'active_students' => 0,
                'total_courses' => 0,
                'published_courses' => 0,
                'total_enrollments' => 0,
                'completion_rate' => 0,
            ],
            'popular_courses' => [],
            'learning_gaps' => [
                'low_completion_courses' => [],
                'low_progress_courses' => [],
            ],
            'course_analytics' => [
                'popularity_report' => [
                    'total_courses' => 0,
                    'total_enrollments' => 0,
                    'popular_courses' => [],
                    'popularity_by_course_category' => [],
                ],
            ],
            'student_analytics' => [
                'performance_report' => [
                    'total_enrollments' => 0,
                    'completed_enrollments' => 0,
                    'average_progress' => 0,
                    'average_completion_time_days' => 0,
                    'performance_by_course' => [],
                ],
                'completion_rates' => [
                    'total_enrollments' => 0,
                    'completed_enrollments' => 0,
                    'completion_rate' => 0,
                ],
                'learning_time_analysis' => [
                    'total_enrollments' => 0,
                    'completed_enrollments' => 0,
                    'average_completion_days' => 0,
                    'total_learning_days' => 0,
                ],
            ],
        ];
    }
}
