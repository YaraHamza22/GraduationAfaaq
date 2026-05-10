<?php

namespace Modules\CommunicationModule\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\CommunicationModule\Models\ChatMessage;

class ChatMessageStored implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.thread.' . $this->message->chat_thread_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.stored';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'chat_thread_id' => $this->message->chat_thread_id,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
            'metadata' => $this->message->metadata,
            'created_at' => optional($this->message->created_at)->toIso8601String(),
        ];
    }
}
