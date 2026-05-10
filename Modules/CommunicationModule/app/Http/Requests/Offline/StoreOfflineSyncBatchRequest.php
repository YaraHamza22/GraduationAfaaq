<?php

namespace Modules\CommunicationModule\Http\Requests\Offline;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfflineSyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:120'],
            'entries' => ['required', 'array', 'min:1', 'max:200'],
            'entries.*.client_event_id' => ['required', 'string', 'max:120'],
            'entries.*.offline_package_id' => ['nullable', 'integer', 'exists:offline_packages,id'],
            'entries.*.action' => ['required', 'string', 'max:60'],
            'entries.*.payload' => ['nullable', 'array'],
            'entries.*.occurred_at' => ['nullable', 'date'],
        ];
    }
}
