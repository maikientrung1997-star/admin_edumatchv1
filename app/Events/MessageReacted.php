<?php

namespace App\Events;

class MessageReacted extends ChatBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'message.reacted';
    }
}
