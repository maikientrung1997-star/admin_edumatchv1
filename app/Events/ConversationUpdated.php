<?php

namespace App\Events;

class ConversationUpdated extends ChatBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }
}
