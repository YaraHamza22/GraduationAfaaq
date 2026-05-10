<?php

namespace Modules\LearningModule\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\UserMangementModule\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseContentAudit extends Model
{
    protected $fillable = [
        'course_id',
        'user_id',
        'lesson_id',
        'verdict',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'user_id' => 'integer',
            'lesson_id' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id', 'lesson_id');
    }
}
