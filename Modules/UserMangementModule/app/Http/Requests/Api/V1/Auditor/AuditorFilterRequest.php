<?php

namespace Modules\UserMangementModule\Http\Requests\Api\V1\Auditor;

use App\Http\Requests\ApiFormRequest;

class AuditorFilterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'term' => 'sometimes|string|max:100',
            'years' => 'sometimes|integer',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
