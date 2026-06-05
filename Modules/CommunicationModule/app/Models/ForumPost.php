<?php

namespace Modules\CommunicationModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
// use Modules\CommunicationModule\Database\Factories\ForumPostFactory;

class ForumPost extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['forum_thread_id', 'author_id', 'body'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['forum_thread_id', 'author_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('forum_post')
            ->setDescriptionForEvent(fn(string $e) => "ForumPost was {$e}");
    }
}
