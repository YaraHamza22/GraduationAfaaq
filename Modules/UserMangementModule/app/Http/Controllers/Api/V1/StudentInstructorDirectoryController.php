<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\UserMangementModule\Http\Requests\Api\V1\Instructor\InstructorFilterRequest;
use Modules\UserMangementModule\Http\Resources\StudentInstructorDirectoryResource;
use Modules\UserMangementModule\Models\User;
use Modules\UserMangementModule\Services\V1\InstructorService;

class StudentInstructorDirectoryController extends Controller
{
    public function __construct(protected InstructorService $instructorService) {}

    public function index(InstructorFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $paginator = $this->instructorService->listForStudentDirectory($filters);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (User $user) => (new StudentInstructorDirectoryResource($user))->resolve()
            )
        );

        return self::paginated($paginator, 'api.instructor.student_directory_list_success');
    }

    public function show(int $instructor): JsonResponse
    {
        $user = $this->instructorService->findPublicForStudent($instructor);

        return self::success(
            (new StudentInstructorDirectoryResource($user))->resolve(),
            'api.instructor.student_directory_one_success'
        );
    }
}
