<?php

namespace Modules\CommunicationModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatThread extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'course_id', 'created_by', 'is_archived', 'archived_at'];

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'chat_thread_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'chat_thread_id')->latestOfMany();
    }
}
