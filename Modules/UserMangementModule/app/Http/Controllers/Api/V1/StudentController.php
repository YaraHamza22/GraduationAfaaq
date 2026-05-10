<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\AssesmentModule\Transformers\QuizResource;
use Modules\LearningModule\Http\Resources\CourseResource;
use Modules\UserMangementModule\Http\Requests\Api\V1\Student\StudentFilterRequest;
use Modules\UserMangementModule\Http\Requests\Api\V1\Student\StudentStoreRequest;
use Modules\UserMangementModule\Http\Requests\Api\V1\Student\StudentUpdateRequest;
use Modules\UserMangementModule\Models\User;
use Modules\UserMangementModule\Services\V1\StudentService;

class StudentController extends Controller
{
    protected StudentService $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;

        $this->middleware('permission:list-students')->only('index');
        $this->middleware('permission:show-student')->only(['show', 'showWithQuizzes', 'showWithCourses']);
        $this->middleware('permission:create-student')->only('store');
        $this->middleware('permission:update-student')->only('update');
        $this->middleware('permission:delete-student')->only('destroy');
    }

    public function index(StudentFilterRequest $request)
    {
        $students = $this->studentService->list($request->validated());

        return self::paginated($students, 'students retrieved successfully');
    }

    public function store(StudentStoreRequest $request)
    {
        $student = $this->studentService->create($request->validated());

        return self::success($student, 'student created successfully', 201);
    }

    public function show(int $id)
    {
        $student = $this->studentService->findById($id);

        return self::success($student);
    }

    /**
     * Student user record and enrolled courses only (no quizzes).
     *
     * @param  User  $student  Route binding: student user's id.
     */
    public function showWithCourses(User $student)
    {
        $payload = $this->studentService->findWithCourses($student);

        return self::success([
            'student' => $payload['student'],
            'courses' => CourseResource::collection($payload['courses']),
        ]);
    }

    /**
     * Student user record (same shape as show), quizzes, and enrolled courses.
     *
     * @param  User  $student  Route binding: student user's id.
     */
    public function showWithQuizzes(User $student)
    {
        $payload = $this->studentService->findWithQuizzes($student);

        return self::success([
            'student' => $payload['student'],
            'quizzes' => QuizResource::collection($payload['quizzes']),
            'courses' => CourseResource::collection($payload['courses']),
        ]);
    }

    /**
     * Authenticated learner: enrolled courses only.
     */
    public function meWithCourses()
    {
        $payload = $this->studentService->findWithCourses(auth()->user());

        return self::success([
            'student' => $payload['student'],
            'courses' => CourseResource::collection($payload['courses']),
        ]);
    }

    /**
     * Authenticated learner: profile, quizzes (assigned / attempted), and enrolled courses.
     */
    public function meWithQuizzes()
    {
        $payload = $this->studentService->findWithQuizzes(auth()->user());

        return self::success([
            'student' => $payload['student'],
            'quizzes' => QuizResource::collection($payload['quizzes']),
            'courses' => CourseResource::collection($payload['courses']),
        ]);
    }

    public function update(StudentUpdateRequest $request, User $student)
    {
        $student = $this->studentService->update($student, $request->validated());

        return self::success($student, 'student updated successfully');
    }

    public function destroy(User $student)
    {
        $this->studentService->delete($student);

        return self::success(null, 'student deleted successfully');
    }
}