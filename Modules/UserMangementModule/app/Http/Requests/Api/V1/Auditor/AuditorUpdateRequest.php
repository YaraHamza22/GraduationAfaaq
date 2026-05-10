<?php

namespace Modules\UserMangementModule\Http\Requests\Api\V1\Auditor;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuditorUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string',
            'password' => ['sometimes', 'string', 'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->symbols()
                    ->letters()
                    ->numbers(),
            ],
            'phone' => 'sometimes|string|phone',
            'date_of_birth' => 'sometimes|date',
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'address' => 'sometimes|nullable|max:500',
            'specialization' => 'sometimes|string|max:255',
            'bio' => 'sometimes|nullable|string|max:1500',
            'years_of_experience' => 'sometimes|integer',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'cv' => 'nullable|mimes:pdf|max:5120',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
