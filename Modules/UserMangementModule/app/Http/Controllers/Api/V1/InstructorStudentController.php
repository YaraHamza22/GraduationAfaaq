<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\LearningModule\Models\CourseInstructor;
use Modules\UserMangementModule\Models\User;

class InstructorStudentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'term' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $instructor = $request->user();
        $limit = $validated['limit'] ?? 50;
        $term = trim((string) ($validated['term'] ?? ''));

        $courseIds = CourseInstructor::query()
            ->where('instructor_id', $instructor->id)
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return self::success([], 'students retrieved successfully');
        }

        $students = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
            ])
            ->whereHas('studentProfile')
            ->whereHas('enrollments', function ($query) use ($courseIds) {
                $query->whereIn('course_id', $courseIds);
            })
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($innerQuery) use ($term) {
                    $innerQuery
                        ->where('users.name', 'like', "%{$term}%")
                        ->orWhere('users.email', 'like', "%{$term}%")
                        ->orWhere('users.phone', 'like', "%{$term}%");
                });
            })
            ->orderBy('users.name')
            ->distinct()
            ->limit($limit)
            ->get()
            ->map(function (User $student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                ];
            })
            ->values();

        return self::success($students, 'students retrieved successfully');
    }
}
