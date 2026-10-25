<?php

namespace App\Events;

class MessageTyping extends ChatBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'message.typing';
    }
}
