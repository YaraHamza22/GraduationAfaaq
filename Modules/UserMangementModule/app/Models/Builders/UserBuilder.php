<?php

namespace Modules\UserMangementModule\Models\Builders;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

class UserBuilder extends Builder
{
    public function byRole(array $roles): self
    {
        return $this->role($roles);
    }

    public function search(string $term)
    {
        return $this->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', "%{$term}%")
                ->orWhere('email', 'LIKE', "%{$term}%");
        });
    }

    public function gender(string $gender)
    {
        return $this->where('gender', $gender);
    }

    /**
     * Apply validated list filters (students, instructors, users).
     *
     * @param  array<string, mixed>  $filters
     */
    public function filters(array $filters = []): self
    {
        if (isset($filters['term']) && is_string($filters['term'])) {
            $term = trim($filters['term']);
            if ($term !== '') {
                $this->search($term);
            }
        }

        if (isset($filters['gender']) && is_string($filters['gender']) && $filters['gender'] !== '') {
            $this->gender($filters['gender']);
        }

        if (! empty($filters['levels']) && is_array($filters['levels'])) {
            $levels = array_values(array_filter(
                $filters['levels'],
                static fn ($level) => $level !== null && $level !== ''
            ));
            if ($levels !== []) {
                $values = array_map(
                    static fn ($level) => $level instanceof BackedEnum ? $level->value : (string) $level,
                    $levels
                );
                $this->whereHas('studentProfile', static function ($q) use ($values): void {
                    $q->whereIn('education_level', $values);
                });
            }
        }

        if (isset($filters['years']) && $filters['years'] !== '' && $filters['years'] !== null) {
            $years = (int) $filters['years'];
            $this->whereHas('instructorProfile', static function ($q) use ($years): void {
                $q->where('years_of_experience', $years);
            });
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filter(array $filters = []): self
    {
        return $this->filters($filters);
    }
}