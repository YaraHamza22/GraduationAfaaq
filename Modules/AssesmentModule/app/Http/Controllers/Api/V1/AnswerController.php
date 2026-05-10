<?php

namespace Modules\AssesmentModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\AssesmentModule\Enums\AttemptStatus;
use Modules\AssesmentModule\Http\Requests\AnswerRequest\BulkStoreAnswersRequest;
use Modules\AssesmentModule\Http\Requests\AnswerRequest\StoreAnswerRequest;
use Modules\AssesmentModule\Http\Requests\AnswerRequest\UpdateAnswerRequest;
use Modules\AssesmentModule\Models\Answer;
use Modules\AssesmentModule\Models\Attempt;
use Modules\AssesmentModule\Services\V1\AnswerService;
use Modules\AssesmentModule\Transformers\AnswerResource;
use Throwable;

/**
 * AnswerController handles CRUD and bulk create for attempt answers.
 *
 * @package Modules\AssesmentModule\Http\Controllers\Api\V1
 */
class AnswerController extends Controller
{
    public function __construct(private AnswerService $answerService)
    {
        $this->middleware('role_or_permission:super-admin|admin|instructor|student|list-answers,api')->only('index');
        $this->middleware('role_or_permission:super-admin|admin|instructor|student|show-answer,api')->only('show');
        $this->middleware('role_or_permission:super-admin|admin|instructor|student|create-answer|submit-answer,api')->only('store', 'bulkStore');
        $this->middleware('role_or_permission:super-admin|admin|instructor|student|update-answer,api')->only('update');
        $this->middleware('role_or_permission:super-admin|admin|instructor|student|delete-answer,api')->only('destroy');
    }

    /**
     * List answers (learners are scoped to their own attempts via `student_id`).
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only([
                'quiz_id',
                'student_id',
                'attempt_id',
                'question_id',
                'selected_option_id',
                'is_correct',
                'graded_by',
                'boolean_answer',
                'min_score',
                'max_score',
                'graded_at',
                'order',
            ]);

            $user = auth()->user();
            if ($user && $user->hasRole('student') && ! $user->hasAnyRole(['super-admin', 'admin', 'instructor'])) {
                $filters['student_id'] = $user->id;
            }

            $perPage = (int) $request->integer('per_page', 15);
            $paginator = $this->answerService->index($filters, $perPage);

            if ($paginator instanceof LengthAwarePaginator) {
                $paginator->getCollection()->transform(
                    fn (Answer $a) => (new AnswerResource($a))->resolve()
                );

                return self::paginated($paginator, 'Operation successful', 200);
            }

            return self::success($paginator, 'Operation successful', 200);
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    public function store(StoreAnswerRequest $request)
    {
        try {
            $answer = $this->answerService->store($request->validated());

            return self::success(new AnswerResource($answer), 'Answer created successfully', 201);
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    /**
     * Create many answers for one attempt in a single transaction.
     */
    public function bulkStore(BulkStoreAnswersRequest $request, Attempt $attempt)
    {
        try {
            $rows = $request->validated()['answers'];
            $created = $this->answerService->bulkStore($attempt, $rows);

            return self::success(
                AnswerResource::collection($created),
                'Answers created successfully',
                201
            );
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    public function show(Answer $answer)
    {
        try {
            $this->assertCanAccessAnswer($answer);
            $answer = $this->answerService->show($answer->id);

            return self::success(new AnswerResource($answer), 'Operation successful', 200);
        } catch (AuthorizationException $e) {
            return self::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    public function update(UpdateAnswerRequest $request, Answer $answer)
    {
        try {
            $answer = $this->answerService->update($answer->id, $request->validated());

            return self::success(new AnswerResource($answer), 'Answer updated successfully', 200);
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    public function destroy(Answer $answer)
    {
        try {
            $this->assertCanModifyAnswerAsLearnerOrStaff($answer);

            $this->answerService->destroy($answer->id);

            return self::success(null, 'Answer deleted successfully', 200);
        } catch (AuthorizationException $e) {
            return self::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    /**
     * Staff may view any answer; learners only their own attempts.
     */
    private function assertCanAccessAnswer(Answer $answer): void
    {
        $user = auth()->user();
        if (! $user) {
            throw new AuthorizationException('Unauthenticated.');
        }

        if ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('instructor')) {
            return;
        }

        $answer->loadMissing('attempt');
        if ((int) $answer->attempt->student_id !== (int) $user->id) {
            throw new AuthorizationException('You may only view answers for your own attempts.');
        }
    }

    /**
     * Staff may delete any answer; learners may delete only while attempt is in progress.
     */
    private function assertCanModifyAnswerAsLearnerOrStaff(Answer $answer): void
    {
        $user = auth()->user();
        if (! $user) {
            throw new AuthorizationException('Unauthenticated.');
        }

        if ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('instructor')) {
            return;
        }

        $answer->loadMissing('attempt');
        if ((int) $answer->attempt->student_id !== (int) $user->id) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($answer->attempt->status !== AttemptStatus::IN_PROGRESS) {
            throw new AuthorizationException('Answers can only be deleted while the attempt is in progress.');
        }
    }
}
