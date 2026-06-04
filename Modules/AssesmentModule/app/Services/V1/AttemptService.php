<?php

namespace Modules\AssesmentModule\Services\V1;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\AssesmentModule\Enums\AttemptStatus;
use Modules\AssesmentModule\Enums\QuestionType;
use Modules\AssesmentModule\Models\Attempt;
use Modules\AssesmentModule\Models\Answer;
use Modules\AssesmentModule\Models\Quiz;
use Modules\AssesmentModule\Transformers\AttemptResource;
use Throwable;

class AttemptService extends BaseService
{
    public function workspace(int $quizId, int $studentId): array
    {
        try {
            $attempt = DB::transaction(function () use ($quizId, $studentId) {
                $quiz = Quiz::query()
                    ->with(['questions.options'])
                    ->findOrFail($quizId);

                $attempts = Attempt::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('student_id', $studentId)
                    ->lockForUpdate()
                    ->get();

                $existing = $attempts
                    ->filter(function (Attempt $attempt) {
                        $status = $attempt->status?->value ?? (string) $attempt->status;
                        return in_array($status, [
                            AttemptStatus::IN_PROGRESS->value,
                            AttemptStatus::PENDING->value,
                        ], true);
                    })
                    ->sortByDesc('id')
                    ->first();

                if (! $existing) {
                    $passed = $attempts->contains(function (Attempt $attempt) {
                        $status = $attempt->status?->value ?? (string) $attempt->status;
                        return $status === AttemptStatus::GRADED->value && $attempt->is_passed === true;
                    });

                    if ($passed) {
                        throw new \RuntimeException('You already passed this quiz.');
                    }

                    $count = $attempts->count();
                    if ($count >= 3) {
                        throw new \RuntimeException('Max attempts reached.');
                    }

                    $attemptNumber = ((int) $attempts->max('attempt_number')) + 1;
                    $existing = Attempt::query()->create([
                        'quiz_id' => $quiz->id,
                        'student_id' => $studentId,
                        'attempt_number' => $attemptNumber,
                        'status' => AttemptStatus::PENDING->value,
                    ]);
                }

                if ($existing->status === AttemptStatus::PENDING) {
                    $existing->update([
                        'status' => AttemptStatus::IN_PROGRESS,
                        'start_at' => $existing->start_at ?? now(),
                        'ends_at' => $existing->ends_at ?? ($quiz->duration_minutes ? now()->addMinutes((int) $quiz->duration_minutes) : null),
                    ]);
                }

                return $existing->fresh(['quiz.questions.options', 'answers']);
            });

            return $this->ok('Quiz workspace opened.', $attempt);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), null, 422);
        } catch (QueryException $e) {
            return $this->fail('Failed to open quiz workspace due to a concurrent request.', $e, 409);
        } catch (Throwable $e) {
            return $this->fail('Failed to open quiz workspace.', $e);
        }
    }

    public function index(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = Attempt::query()
                ->with([
                    'quiz:id,instructor_id,title,passing_score,max_score',
                    'student:id,name,email',
                    'grader:id,name,email',
                ])
                ->when(
                    $filters['student_query'] ?? null,
                    fn ($q, $term) => $q->whereHas('student', function ($studentQuery) use ($term) {
                        $studentQuery
                            ->where('name', 'like', '%' . trim((string) $term) . '%')
                            ->orWhere('email', 'like', '%' . trim((string) $term) . '%');
                    })
                )
                ->filter($filters);

            $data = $query->paginate($perPage);

            return $this->ok('Attempts fetched successfully.', $data);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch attempts.', $e);
        }
    }

    public function show(Attempt $attempt): array
    {
        try {
            $attempt->load(['quiz.questions.options', 'answers', 'student:id,name,email', 'grader:id,name,email']);

            return $this->ok('Attempt fetched successfully.', $attempt);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch attempt.', $e);
        }
    }

    public function resultsForQuiz(Quiz $quiz, array $filters = [], int $perPage = 15, ?Authenticatable $viewer = null): array
    {
        try {
            if ($viewer && ! $viewer->hasAnyRole(['super-admin', 'admin'])) {
                if ((int) $quiz->instructor_id !== (int) $viewer->id) {
                    return $this->fail('You are not allowed to view results for this quiz.', null, 403);
                }
            }

            $filters['quiz_id'] = $quiz->id;
            $filters['order'] = $filters['order'] ?? 'latest';

            $query = Attempt::query()
                ->with([
                    'student:id,name,email',
                    'grader:id,name,email',
                    'quiz:id,instructor_id,title,passing_score,max_score',
                ])
                ->withCount('answers')
                ->filter($filters)
                ->when(
                    $filters['student_query'] ?? null,
                    fn ($q, $term) => $q->whereHas('student', function ($studentQuery) use ($term) {
                        $studentQuery
                            ->where('name', 'like', '%' . trim((string) $term) . '%')
                            ->orWhere('email', 'like', '%' . trim((string) $term) . '%');
                    })
                );

            $paginator = $query->paginate($perPage);
            $results = AttemptResource::collection($paginator->getCollection())->resolve();

            $statusCounts = Attempt::query()
                ->where('quiz_id', $quiz->id)
                ->whereIn('status', [AttemptStatus::SUBMITTED->value, AttemptStatus::GRADED->value])
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->ok('Quiz results fetched successfully.', [
                'quiz' => [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'passing_score' => $quiz->passing_score,
                    'max_score' => $quiz->max_score,
                    'instructor_id' => $quiz->instructor_id,
                ],
                'summary' => [
                    'submitted_count' => (int) ($statusCounts[AttemptStatus::SUBMITTED->value] ?? 0),
                    'graded_count' => (int) ($statusCounts[AttemptStatus::GRADED->value] ?? 0),
                    'results_count' => $paginator->total(),
                ],
                'results' => $results,
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => count($paginator),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch quiz results.', $e);
        }
    }

    public function store(array $data): array
    {
        try {
            $quiz = Quiz::query()->findOrFail($data['quiz_id']);
            $studentId = $data['student_id'] ?? Auth::id();

            $attempt = DB::transaction(function () use ($quiz, $studentId) {
                $attempts = Attempt::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('student_id', $studentId)
                    ->lockForUpdate()
                    ->get();

                $passed = $attempts->contains(function (Attempt $attempt) {
                    $status = $attempt->status?->value ?? (string) $attempt->status;
                    return $status === AttemptStatus::GRADED->value && $attempt->is_passed === true;
                });

                if ($passed) {
                    return null;
                }

                $open = $attempts->contains(function (Attempt $attempt) {
                    $status = $attempt->status?->value ?? (string) $attempt->status;
                    return in_array($status, [
                        AttemptStatus::PENDING->value,
                        AttemptStatus::IN_PROGRESS->value,
                        AttemptStatus::SUBMITTED->value,
                    ], true);
                });

                if ($open) {
                    throw new \RuntimeException('You already have an open attempt.');
                }

                $count = $attempts->count();
                if ($count >= 3) {
                    throw new \RuntimeException('Max attempts reached.');
                }

                $num = ((int) $attempts->max('attempt_number')) + 1;

                return Attempt::query()->create([
                    'quiz_id' => $quiz->id,
                    'student_id' => $studentId,
                    'attempt_number' => $num,
                    'status' => AttemptStatus::PENDING->value,
                ]);
            });

            if (!$attempt) {
                return $this->fail('You already passed this quiz.', null, 422);
            }

            return $this->ok('Attempt created.', $attempt);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), null, 422);
        } catch (QueryException $e) {
            return $this->fail('Failed to create attempt due to a concurrent request.', $e, 409);
        } catch (Throwable $e) {
            return $this->fail('Failed to create attempt.', $e);
        }
    }

    public function start(Attempt $attempt): array
    {
        if ($attempt->status !== AttemptStatus::PENDING) {
            return $this->fail('Invalid attempt state.', null, 422);
        }

        $attempt->loadMissing('quiz:id,duration_minutes');

        $attempt->update([
            'status' => AttemptStatus::IN_PROGRESS,
            'start_at' => now(),
            'ends_at' => $attempt->quiz->duration_minutes
                ? now()->addMinutes((int) $attempt->quiz->duration_minutes)
                : null,
        ]);

        return $this->ok('Started.', $attempt);
    }

    public function update(Attempt $attempt, array $data): array
    {
        $attempt->update($data);

        return $this->ok('Attempt updated.', $attempt->fresh());
    }

    public function submit(Attempt $attempt, array $data): array
    {
        if ($attempt->status !== AttemptStatus::IN_PROGRESS) {
            return $this->fail('Invalid state.', null, 422);
        }

        // Eager-load quiz with all its questions+options in one shot to avoid
        // N+1 queries inside the foreach loop below.
        $attempt->loadMissing([
            'quiz:id,passing_score,auto_grade_enabled',
            'quiz.questions:id,quiz_id,type,point',
            'quiz.questions.options:id,question_id,is_correct',
        ]);

        $quiz      = $attempt->quiz;
        $questions = $quiz->questions->keyBy('id');

        $hasManual = false;
        DB::transaction(function () use ($attempt, $data, $questions, $quiz, &$hasManual) {

            foreach ($data['answers'] as $ans) {
                $question = $questions->get((int) $ans['question_id']);

                if (! $question) {
                    continue;
                }

                $answer = Answer::updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'selected_option_id' => $ans['selected_option_id'] ?? null,
                        'answer_text'        => $ans['answer_text'] ?? null,
                        'boolean_answer'     => $ans['boolean_answer'] ?? null,
                    ]
                );

                if ($question->type === QuestionType::MULTIPLE_CHOICE
                    || $question->type === QuestionType::TRUE_FALSE) {

                    $opt     = $question->options->firstWhere('id', $ans['selected_option_id']);
                    $correct = $opt?->is_correct ?? false;

                    $answer->update([
                        'is_correct'     => $correct,
                        'question_score' => $correct ? $question->point : 0,
                    ]);
                }

                if ($question->type === QuestionType::SHORT_ANSWER) {
                    $hasManual = true;
                    $answer->update([
                        'is_correct'     => null,
                        'question_score' => 0,
                    ]);
                }
            }

            // Calculate score from in-memory collection to avoid an extra query.
            $score                = (int) $attempt->answers()->sum('question_score');
            $requiresManualReview = $hasManual || $quiz->auto_grade_enabled === false;

            if ($requiresManualReview) {
                $attempt->update([
                    'status'       => AttemptStatus::SUBMITTED,
                    'score'        => $score,
                    'submitted_at' => now(),
                ]);
            } else {
                $attempt->update([
                    'status'       => AttemptStatus::GRADED,
                    'score'        => $score,
                    'is_passed'    => $score >= $quiz->passing_score,
                    'submitted_at' => now(),
                    'graded_at'    => now(),
                ]);
            }
        });

        return $this->ok('Submitted.', $attempt->fresh());
    }

    public function grade(Attempt $attempt, array $data): array
    {
        try {
            $attempt->loadMissing([
                'quiz:id,instructor_id,passing_score,max_score,title',
                'quiz.questions:id,quiz_id,point',
                'student:id,name,email',
                'answers',
            ]);

            if ($attempt->status !== AttemptStatus::SUBMITTED) {
                return $this->fail('Only submitted attempts can be graded.', null, 422);
            }

            $gradedBy  = Auth::id();
            $questions = $attempt->quiz->questions->keyBy('id');

            // Index the already-loaded answers collection by question_id to avoid
            // one query per answer inside the loop.
            $answersMap = $attempt->answers->keyBy('question_id');

            $providedQuestionIds = collect($data['answers'])->pluck('question_id');
            if ($providedQuestionIds->duplicates()->isNotEmpty()) {
                return $this->fail('Each question can only be graded once per request.', null, 422);
            }

            DB::transaction(function () use ($attempt, $data, $questions, $answersMap, $gradedBy) {
                foreach ($data['answers'] as $ans) {
                    $question = $questions->get((int) $ans['question_id']);
                    if (! $question) {
                        throw new ModelNotFoundException('Question does not belong to this quiz.');
                    }

                    $answer = $answersMap->get((int) $ans['question_id']);
                    if (! $answer) {
                        throw new ModelNotFoundException('Answer not found for the provided question.');
                    }

                    $earnedScore = (int) $ans['earned_score'];
                    if ($earnedScore > (int) $question->point) {
                        throw new \InvalidArgumentException(sprintf(
                            'Earned score for question %d cannot exceed the question point value of %d.',
                            $question->id,
                            $question->point
                        ));
                    }

                    $answer->update([
                        'question_score' => $earnedScore,
                        'is_correct'     => (bool) $ans['is_correct'],
                        'graded_by'      => $gradedBy,
                        'graded_at'      => now(),
                    ]);
                }

                // Re-sum from DB after all updates are persisted.
                $score = (int) $attempt->answers()->sum('question_score');

                $attempt->update([
                    'status'     => AttemptStatus::GRADED,
                    'score'      => $score,
                    'is_passed'  => $score >= $attempt->quiz->passing_score,
                    'graded_by'  => $gradedBy,
                    'graded_at'  => now(),
                ]);
            });

            return $this->ok('Graded.', $attempt->fresh(['quiz', 'student', 'grader', 'answers']));
        } catch (ModelNotFoundException $e) {
            return $this->fail($e->getMessage(), $e, 404);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), $e, 422);
        } catch (Throwable $e) {
            return $this->fail('Failed to grade attempt.', $e);
        }
    }

    public function destroy(Attempt $attempt): array
    {
        $attempt->delete();
        return $this->ok('Deleted.');
    }
}
