<?php

namespace Modules\CommunicationModule\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\CommunicationModule\Events\ChatMessageStored;
use Modules\CommunicationModule\Models\ChatParticipant;
use Modules\CommunicationModule\Services\V1\NotificationService;

class SendChatMessageInAppNotification implements ShouldQueue
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function handle(ChatMessageStored $event): void
    {
        $participantIds = ChatParticipant::query()
            ->where('chat_thread_id', $event->message->chat_thread_id)
            ->where('user_id', '!=', $event->message->sender_id)
            ->pluck('user_id');

        foreach ($participantIds as $participantId) {
            $this->notificationService->sendChatMessageNotification((int) $participantId, $event->message);
        }
    }
}
