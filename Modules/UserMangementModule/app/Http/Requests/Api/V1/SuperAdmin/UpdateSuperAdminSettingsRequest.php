<?php

namespace Modules\UserMangementModule\Http\Requests\Api\V1\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuperAdminSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_language' => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'notifications' => ['sometimes', 'array'],
            'notifications.in_app' => ['sometimes', 'boolean'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.digest' => ['sometimes', 'boolean'],
            'integrations' => ['sometimes', 'array'],
            'integrations.zoom_enabled' => ['sometimes', 'boolean'],
            'integrations.google_classroom_enabled' => ['sometimes', 'boolean'],
            'integrations.webhook_secret_rotation_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }
}
