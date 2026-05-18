<?php

namespace Modules\UserMangementModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\UserMangementModule\Models\User;

/** @mixin User */
class StudentInstructorDirectoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gender' => $this->gender,
            'avatar_url' => $this->getFirstMediaUrl('avatar'),
            'profile' => [
                'specialization' => $this->instructorProfile?->specialization,
                'bio' => $this->instructorProfile?->bio,
                'years_of_experience' => $this->instructorProfile?->years_of_experience,
            ],
        ];
    }
}
