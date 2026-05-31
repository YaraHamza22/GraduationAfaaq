<?php

namespace Modules\AssesmentModule\Services\V1;

use Illuminate\Support\Facades\DB;
use Modules\AssesmentModule\Events\CourseCertificateIssued;
use Modules\AssesmentModule\Models\CourseCertificate;

class CertificateEligibilityService
{
    public function evaluateAndIssue(int $courseId, int $studentId, array $progress): array
    {
        $eligible = ($progress['required_quizzes_count'] ?? 0) > 0
            && ($progress['all_required_quizzes_graded'] ?? false) === true
            && (float) ($progress['average_percentage'] ?? 0) >= 60.0;

        $certificate = CourseCertificate::query()
            ->where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->first();

        if (!$eligible) {
            return [
                'eligible' => false,
                'issued' => (bool) $certificate,
                'certificate_id' => $certificate?->id,
                'issued_at' => $certificate?->issued_at,
                'average_percentage' => $progress['average_percentage'] ?? 0,
                'reason' => 'The student must complete all required graded quizzes and achieve an arithmetic average of at least 60%.',
            ];
        }

        $created = false;
        $certificate = DB::transaction(function () use ($courseId, $studentId, $progress, &$created) {
            $certificate = CourseCertificate::query()->firstOrCreate(
                [
                    'course_id' => $courseId,
                    'student_id' => $studentId,
                ],
                [
                    'weighted_percentage' => $progress['average_percentage'],
                    'issued_at' => now(),
                ]
            );

            if (!$certificate->issued_at) {
                $certificate->update([
                    'issued_at' => now(),
                    'weighted_percentage' => $progress['average_percentage'],
                ]);
            }

            $created = $certificate->wasRecentlyCreated;

            return $certificate;
        });

        if ($created) {
            event(new CourseCertificateIssued($certificate));
        }

        return [
            'eligible' => true,
            'issued' => true,
            'certificate_id' => $certificate->id,
            'issued_at' => $certificate->issued_at,
            'average_percentage' => $progress['average_percentage'],
        ];
    }
}
