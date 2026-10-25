<?php

namespace App\Events;

class ChatMessageSent extends ChatBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
