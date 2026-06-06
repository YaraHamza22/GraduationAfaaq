<?php

namespace Modules\CommunicationModule\Http\Requests\VirtualSession;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVirtualSessionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'course_id' => ['sometimes', 'nullable', 'integer', 'exists:courses,course_id'],
            'integration_id' => ['sometimes', 'nullable', 'integer', 'exists:external_integrations,id'],
            'provider' => ['sometimes', 'string', 'in:afaq_live,zoom,google_meet,google_classroom'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'array'],
            'status' => ['sometimes', 'string', 'in:draft,published,cancelled,completed'],
            'join_url' => ['sometimes', 'nullable', 'url'],
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
