<?php

namespace Modules\AssesmentModule\Services\V1;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AssesmentModule\Models\Answer;
use Modules\AssesmentModule\Models\Attempt;
use Throwable;

/**
 * AnswerService handles the business logic for managing answers, including:
 * - Storing new answers.
 * - Retrieving a specific answer by ID.
 * - Updating an existing answer.
 * - Deleting an answer.
 *
 * @package Modules\AssesmentModule\Services\V1
 */
class AnswerService extends BaseService
{
    /**
     * Payload keys accepted from clients (per row in bulk); `attempt_id` is always set server-side.
     *
     * @var list<string>
     */
    private const ANSWER_ROW_KEYS = [
        'question_id',
        'selected_option_id',
        'answer_text',
        'boolean_answer',
        'is_correct',
        'question_score',
        'graded_by',
        'graded_at',
    ];

    /**
     * Fetch a paginated list of answers based on the given filters.
     *
     * @param array $filters The filters to apply to the answer query (e.g., student_id, quiz_id).
     * @param int $perPage The number of answers per page (default is 15).
     * @return mixed The paginated list of answers.
     *
     * @throws \Exception If an error occurs while fetching the answers.
     */
    public function index(array $filters = [], int $perPage = 15)
    {
        try {
            return Answer::query()
                ->with(['attempt:id,quiz_id,student_id,status', 'question:id,quiz_id,type', 'grader:id,name,email'])
                ->filter($filters)
                ->paginate($perPage);
        } catch (Throwable $e) {
            throw new \Exception('Failed to fetch answers: ' . $e->getMessage());
        }
    }

    /**
     * Store a new answer with the provided data.
     *
     * @param array $data The data to create the answer.
     * @return \Modules\AssesmentModule\Models\Answer The created answer.
     *
     * @throws Throwable If an error occurs while saving the answer.
     */
    public function store(array $data)
    {
        try {
            $answer = Answer::create($data);

            return $answer->load(['attempt:id,quiz_id,student_id,status', 'question:id,quiz_id,type', 'grader:id,name,email']);
        } catch (Throwable $e) {
            throw new \Exception('Failed to create answer: ' . $e->getMessage());
        }
    }

    /**
     * Create many answers for one attempt (validated upstream).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, Answer>
     */
    public function bulkStore(Attempt $attempt, array $rows): Collection
    {
        try {
            return DB::transaction(function () use ($attempt, $rows) {
                $created = collect();
                $rowKeys = array_flip(self::ANSWER_ROW_KEYS);

                foreach ($rows as $row) {
                    $payload = array_merge(
                        ['attempt_id' => $attempt->id],
                        array_intersect_key($row, $rowKeys)
                    );
                    $created->push(Answer::create($payload));
                }

                // Load all relationships in 3 queries (one per relation) instead
                // of 3 × N queries (one per answer per relation).
                $created->loadMissing([
                    'attempt:id,quiz_id,student_id,status',
                    'question:id,quiz_id,type',
                    'grader:id,name,email',
                ]);

                return $created;
            });
        } catch (Throwable $e) {
            throw new \Exception('Failed to bulk create answers: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve a specific answer by its ID.
     *
     * @param int $id The ID of the answer to retrieve.
     * @return \Modules\AssesmentModule\Models\Answer The retrieved answer.
     *
     * @throws Throwable If an error occurs while retrieving the answer.
     */
    public function show($id)
    {
        try {
            $answer = Answer::query()
                ->with(['attempt:id,quiz_id,student_id,status', 'question:id,quiz_id,type', 'grader:id,name,email'])
                ->find($id);

            if (!$answer) {
                throw new \Exception('Answer not found');
            }

            return $answer;
        } catch (Throwable $e) {
            throw new \Exception('Failed to retrieve answer: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing answer with the provided data.
     *
     * @param int $id The ID of the answer to update.
     * @param array $data The data to update the answer.
     * @return \Modules\AssesmentModule\Models\Answer The updated answer.
     *
     * @throws Throwable If an error occurs while updating the answer.
     */
    public function update($id, array $data)
    {
        try {
            $answer = Answer::findOrFail($id);

            $answer->update($data);

            return $answer->fresh(['attempt:id,quiz_id,student_id,status', 'question:id,quiz_id,type', 'grader:id,name,email']);
        } catch (Throwable $e) {
            throw new \Exception('Failed to update answer: ' . $e->getMessage());
        }
    }

    /**
     * Delete an answer by its ID.
     *
     * @param int $id The ID of the answer to delete.
     * @return void
     *
     * @throws Throwable If an error occurs while deleting the answer.
     */
    public function destroy($id)
    {
        try {
            $answer = Answer::findOrFail($id);
            $answer->delete();
        } catch (Throwable $e) {
            throw new \Exception('Failed to delete answer: ' . $e->getMessage());
        }
    }
}
