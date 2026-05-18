<?php

namespace Modules\UserMangementModule\Http\Requests\Api\V1\Instructor;

use App\Http\Requests\ApiFormRequest;

class InstructorFilterRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'term' => ['sometimes', 'nullable', 'string', 'max:100'],
            'years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}