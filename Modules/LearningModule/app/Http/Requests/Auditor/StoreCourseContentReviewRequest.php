<?php

namespace Modules\LearningModule\Http\Requests\Auditor;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Modules\LearningModule\Models\Course;

class StoreCourseContentReviewRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');

        return [
            'verdict' => ['required', 'string', Rule::in(['approved', 'changes_requested', 'follow_up'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'lesson_id' => [
                'nullable',
                'integer',
                Rule::exists('lessons', 'lesson_id')->where(function ($query) use ($course) {
                    $query->whereNull('deleted_at')
                        ->whereIn('unit_id', function ($sub) use ($course) {
                            $sub->select('unit_id')
                                ->from('units')
                                ->where('course_id', $course->course_id)
                                ->whereNull('deleted_at');
                        });
                }),
            ],
        ];
    }
}
