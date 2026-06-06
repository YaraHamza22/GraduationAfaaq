<?php

namespace Modules\CommunicationModule\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VirtualSessionSignalSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly array $payload
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("afaq-live.{$this->sessionId}");
    }

    public function broadcastAs(): string
    {
        return 'virtual-session.signal';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
