<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

abstract class ChatBroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(public string $channel, public array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
