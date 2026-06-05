<?php

namespace Modules\CommunicationModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserMangementModule\Models\User;
use App\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
// use Modules\CommunicationModule\Database\Factories\ChatMessageFactory;

class ChatMessage extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['chat_thread_id', 'sender_id', 'body', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['chat_thread_id', 'sender_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('chat_message')
            ->setDescriptionForEvent(fn(string $e) => "ChatMessage was {$e}");
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatMessageRead::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
