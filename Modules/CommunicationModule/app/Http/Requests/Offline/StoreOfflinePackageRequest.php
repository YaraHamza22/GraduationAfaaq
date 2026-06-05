<?php

namespace Modules\CommunicationModule\Http\Requests\Offline;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOfflinePackageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,course_id'],
            'version' => ['sometimes', 'string', 'max:60'],
            'manifest' => ['sometimes', 'array'],
            'file_url' => ['nullable', 'url', 'required_without:package_file'],
            'package_file' => ['nullable', 'file', 'required_without:file_url', 'mimes:zip,bin,tar,gz'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $version = $this->input('version');

        $this->merge([
            'version' => filled($version) ? $version : 'pkg-'.Str::lower(Str::random(8)),
            'manifest' => $this->input('manifest', []),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
