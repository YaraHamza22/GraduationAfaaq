<?php

namespace Modules\CommunicationModule\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BelongsToRelation;

class ChatParticipant extends Model
{
    use HasFactory;

    protected $fillable = ['chat_thread_id', 'user_id', 'role'];

    public function thread(): BelongsToRelation
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
