<?php

namespace Modules\AssesmentModule\Http\Requests\AnswerRequest;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;
use Modules\AssesmentModule\Enums\AttemptStatus;
use Modules\AssesmentModule\Enums\QuestionType;
use Modules\AssesmentModule\Models\Answer;
use Modules\AssesmentModule\Models\Attempt;
use Modules\AssesmentModule\Models\Question;
use Modules\AssesmentModule\Models\QuestionOption;

/**
 * Validates a batch of answers for one in-progress attempt (same rules as single {@see StoreAnswerRequest} rows).
 */
class BulkStoreAnswersRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:500'],
            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
            'answers.*.selected_option_id' => ['nullable', 'integer', 'exists:question_options,id'],
            'answers.*.answer_text' => ['nullable', 'array'],
            'answers.*.answer_text.*' => ['nullable', 'string'],
            'answers.*.boolean_answer' => ['nullable', 'boolean'],
            'answers.*.is_correct' => ['sometimes', 'boolean'],
            'answers.*.question_score' => ['sometimes', 'integer', 'min:0'],
            'answers.*.graded_at' => ['sometimes', 'date'],
            'answers.*.graded_by' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $attempt = $this->route('attempt');
            if (! $attempt instanceof Attempt) {
                return;
            }

            $user = auth()->user();
            if ($user && ! ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('instructor'))) {
                if ((int) $attempt->student_id !== (int) $user->id) {
                    $v->errors()->add('attempt_id', 'You may only submit answers for your own attempt.');

                    return;
                }
            }

            if ($attempt->status !== AttemptStatus::IN_PROGRESS) {
                $v->errors()->add('answers', 'Answers can only be submitted while the attempt is in progress.');

                return;
            }

            $answers = $this->input('answers', []);
            $questionIds = collect($answers)->pluck('question_id')->filter()->map(fn ($id) => (int) $id);
            if ($questionIds->count() !== $questionIds->unique()->count()) {
                $v->errors()->add('answers', 'Duplicate question_id values in the same request are not allowed.');

                return;
            }

            foreach ($questionIds as $qid) {
                if (Answer::query()->where('attempt_id', $attempt->id)->where('question_id', $qid)->exists()) {
                    $v->errors()->add('answers', "Question {$qid} already has an answer for this attempt.");

                    return;
                }
            }

            foreach ($answers as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $this->validateAnswerRowAtIndex($v, $attempt, $row, (string) $index);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateAnswerRowAtIndex(Validator $v, Attempt $attempt, array $row, string $index): void
    {
        $prefix = "answers.{$index}";
        $questionId = (int) ($row['question_id'] ?? 0);
        $question = Question::query()->find($questionId);
        if (! $question) {
            return;
        }

        if ((int) $attempt->quiz_id !== (int) $question->quiz_id) {
            $v->errors()->add($prefix, 'Question does not belong to this attempt quiz.');

            return;
        }

        $type = $question->type;
        $selected = $row['selected_option_id'] ?? null;
        $text = $row['answer_text'] ?? null;
        $bool = $row['boolean_answer'] ?? null;

        if ($type === QuestionType::MULTIPLE_CHOICE) {
            if ($selected === null) {
                $v->errors()->add("{$prefix}.selected_option_id", 'selected_option_id is required for multiple choice questions.');

                return;
            }
            $belongs = QuestionOption::query()
                ->where('id', (int) $selected)
                ->where('question_id', $questionId)
                ->exists();
            if (! $belongs) {
                $v->errors()->add("{$prefix}.selected_option_id", 'The selected option does not belong to this question.');
            }

            return;
        }

        if ($type === QuestionType::TRUE_FALSE) {
            if ($bool === null) {
                $v->errors()->add("{$prefix}.boolean_answer", 'boolean_answer is required for true/false questions.');
            }

            return;
        }

        if ($type === QuestionType::SHORT_ANSWER) {
            $hasText = is_array($text) && collect($text)->filter(fn ($x) => is_string($x) && trim($x) !== '')->isNotEmpty();
            if (! $hasText) {
                $v->errors()->add("{$prefix}.answer_text", 'answer_text is required for short answer questions.');
            }
        }
    }
}
