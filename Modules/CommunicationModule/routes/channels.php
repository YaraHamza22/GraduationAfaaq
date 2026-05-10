<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\CommunicationModule\Models\ChatParticipant;

Broadcast::channel('chat.thread.{threadId}', function ($user, int $threadId) {
    return ChatParticipant::query()
        ->where('chat_thread_id', $threadId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('notifications.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
