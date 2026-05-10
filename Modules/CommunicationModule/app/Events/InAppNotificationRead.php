<?php

namespace Modules\CommunicationModule\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\SerializesModels;

class InAppNotificationRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DatabaseNotification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->notification->notifiable_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.read';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'read_at' => optional($this->notification->read_at)->toIso8601String(),
        ];
    }
}
