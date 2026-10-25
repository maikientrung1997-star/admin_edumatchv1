<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatParticipant extends Model
{
    use HasFactory;

    protected $table = 'chat_participants';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_at',
        'is_muted',
        'joined_at',
        'notes', 'role', 'last_read_message_id',
    ];

    protected $attributes = [
        'is_muted' => false,
    ];

    protected $casts = [
        'id' => 'integer',
        'last_read_message_id' => 'integer',
        'conversation_id' => 'integer',
        'user_id' => 'integer',
        'last_read_at' => 'datetime',
        'is_muted' => 'boolean',
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
