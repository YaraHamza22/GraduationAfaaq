<?php

namespace Modules\CommunicationModule\Observers;

use Modules\CommunicationModule\Events\ChatMessageStored;
use Modules\CommunicationModule\Models\ChatMessage;

class ChatMessageObserver
{
    public function created(ChatMessage $chatMessage): void
    {
        event(new ChatMessageStored($chatMessage->fresh()));
    }
}
