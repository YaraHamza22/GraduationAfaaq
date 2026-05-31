<?php

namespace Modules\AssesmentModule\Services\V1;

use Modules\AssesmentModule\Enums\AttemptStatus;
use Modules\AssesmentModule\Enums\QuizStatus;
use Modules\AssesmentModule\Enums\QuizType;
use Modules\AssesmentModule\Models\Quiz;
use Modules\LearningModule\Models\Lesson;
use Modules\LearningModule\Models\Enrollment;
use Modules\LearningModule\Models\Unit;

class CourseQuizProgressService
{
    public function courseQuizAvailabilityForStudent(int $courseId, int $studentId): array
    {
        $isEnrolled = Enrollment::query()
            ->where('course_id', $courseId)
            ->where('learner_id', $studentId)
            ->where('enrollment_status', 'active')
            ->exists();

        if (! $isEnrolled) {
            return [
                'course_id' => $courseId,
                'student_id' => $studentId,
                'is_enrolled' => false,
                'has_quiz' => false,
                'quizzes_count' => 0,
            ];
        }

        $unitIds = Unit::query()->where('course_id', $courseId)->pluck('unit_id');
        $lessonIds = Lesson::query()->whereIn('unit_id', $unitIds)->pluck('lesson_id');

        $quizzesCount = Quiz::query()
            ->where('status', QuizStatus::PUBLISHED->value)
            ->where(function ($query) use ($courseId, $unitIds, $lessonIds) {
                $query->where(function ($q) use ($courseId) {
                    $q->where('quizable_type', QuizType::COURSE->value)
                        ->where('quizable_id', $courseId);
                })->orWhere(function ($q) use ($unitIds) {
                    $q->where('quizable_type', QuizType::UNIT->value)
                        ->whereIn('quizable_id', $unitIds);
                })->orWhere(function ($q) use ($lessonIds) {
                    $q->where('quizable_type', QuizType::LESSON->value)
                        ->whereIn('quizable_id', $lessonIds);
                });
            })
            ->count();

        return [
            'course_id' => $courseId,
            'student_id' => $studentId,
            'is_enrolled' => true,
            'has_quiz' => $quizzesCount > 0,
            'quizzes_count' => $quizzesCount,
        ];
    }

    public function build(int $courseId, int $studentId): array
    {
        $unitIds = Unit::query()
            ->where('course_id', $courseId)
            ->pluck('unit_id');

        $lessonIds = Lesson::query()
            ->whereIn('unit_id', $unitIds)
            ->pluck('lesson_id');

        $quizzes = Quiz::query()
            ->where('status', QuizStatus::PUBLISHED->value)
            ->where(function ($query) use ($courseId, $unitIds, $lessonIds) {
                $query->where(function ($q) use ($courseId) {
                    $q->where('quizable_type', QuizType::COURSE->value)
                        ->where('quizable_id', $courseId);
                })->orWhere(function ($q) use ($unitIds) {
                    $q->where('quizable_type', QuizType::UNIT->value)
                        ->whereIn('quizable_id', $unitIds);
                })->orWhere(function ($q) use ($lessonIds) {
                    $q->where('quizable_type', QuizType::LESSON->value)
                        ->whereIn('quizable_id', $lessonIds);
                });
            })
            ->with(['attempts' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->where('status', AttemptStatus::GRADED->value)
                    ->orderByDesc('score')
                    ->orderByDesc('id');
            }])
            ->get();

        $quizRows = $quizzes->map(function (Quiz $quiz) {
            $gradedAttempts = $quiz->attempts;
            $bestGradedAttempt = $gradedAttempts->first();

            $bestScore = (float) ($bestGradedAttempt?->score ?? 0);
            $maxScore = (float) ($quiz->max_score ?? 0);
            $passingScore = (float) ($quiz->passing_score ?? 0);
            $quizPercentage = $maxScore > 0
                ? round(($bestScore / $maxScore) * 100, 2)
                : 0.0;
            $isGraded = $bestGradedAttempt !== null;
            $isPassed = $isGraded && (
                $bestGradedAttempt->is_passed === true
                || $bestScore >= $passingScore
            );

            return [
                'quiz_id' => $quiz->id,
                'quizable_type' => $quiz->quizable_type,
                'quizable_id' => $quiz->quizable_id,
                'max_score' => $maxScore,
                'passing_score' => $passingScore,
                'best_score' => $bestScore,
                'quiz_percentage' => $quizPercentage,
                'attempts_used' => $gradedAttempts->count(),
                'attempts_left' => max(0, 3 - $gradedAttempts->count()),
                'is_graded' => $isGraded,
                'is_passed' => $isPassed,
                'pass_lock' => $isPassed,
            ];
        });

        $requiredQuizzesCount = $quizzes->count();
        $averagePercentage = $requiredQuizzesCount > 0
            ? round($quizRows->sum('quiz_percentage') / $requiredQuizzesCount, 2)
            : 0.0;
        $earnedTotal = (float) $quizRows->sum('best_score');
        $maxTotal = (float) $quizRows->sum('max_score');
        $weightedPercentage = $maxTotal > 0
            ? round(($earnedTotal / $maxTotal) * 100, 2)
            : 0.0;
        $allQuizzesGraded = $quizRows->every(
            fn (array $quizRow) => $quizRow['is_graded'] === true
        );

        return [
            'course_id' => $courseId,
            'student_id' => $studentId,
            'required_quizzes_count' => $requiredQuizzesCount,
            'all_required_quizzes_graded' => $allQuizzesGraded,
            'passed_required_quizzes_count' => $quizRows->where('is_passed', true)->count(),
            'failed_required_quizzes_count' => $quizRows->where('is_passed', false)->count(),
            'earned_total' => $earnedTotal,
            'max_total' => $maxTotal,
            'weighted_percentage' => $weightedPercentage,
            'average_percentage' => $averagePercentage,
            'quizzes' => $quizRows->values()->all(),
        ];
    }
}

