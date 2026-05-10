<?php

namespace Modules\CommunicationModule\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['nullable', 'array', 'min:1', 'required_without_all:role_names,all_users'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role_names' => ['nullable', 'array', 'min:1', 'required_without_all:user_ids,all_users'],
            'role_names.*' => ['string', 'exists:roles,name'],
            'all_users' => ['nullable', 'boolean', 'required_without_all:user_ids,role_names'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:60'],
            'data' => ['nullable', 'array'],
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
