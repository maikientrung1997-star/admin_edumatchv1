<?php

namespace App\Support;

use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Str;

class ChatData
{
    public const MESSAGE_RELATIONS = ['sender.center', 'chatAttachments', 'reactions'];

    public const CONVERSATION_RELATIONS = ['chatParticipants.user.center', 'lastMessage.sender.center', 'lastMessage.chatAttachments'];

    public static function user(?User $user, ?int $id = null, bool $details = false): array
    {
        if (! $user) {
            return ['id' => $id, 'name' => 'Tài khoản không còn hoạt động', 'avatar_url' => null, 'role' => null, 'status' => 'inactive'];
        }
        $data = ['id' => $user->id, 'name' => $user->role === 'center' ? ($user->center?->name ?? $user->name) : $user->name,
            'avatar_url' => $user->avatar_url, 'role' => $user->role, 'status' => $user->status];
        if ($details) {
            $profile = match ($user->role) {
                'student' => $user->studentProfile, 'teacher' => $user->teacherProfile, 'center' => $user->center, default => null
            };
            $data += ['bio' => $profile?->bio, 'headline' => $profile?->headline,
                'phone' => $user->role === 'center' ? $profile?->hotline : ($user->role === 'teacher' && $profile?->show_phone ? $user->phone : null),
                'location' => $profile ? collect([$profile->ward?->name, $profile->district?->name, $profile->province?->name])->filter()->join(', ') : '',
                'verification_status' => $profile?->verification_status ?? 'unverified',
                'profile_url' => in_array($user->role, ['teacher', 'center'], true) ? '/giao-vien/'.$user->id : null,
                'interests' => $user->role === 'student' ? $user->studentLearningNeeds->where('is_active', true)->map(fn ($need) => [
                    'subject' => $need->subject?->name, 'level' => $need->level?->name, 'goals' => $need->goals,
                ])->values()->all() : []];
        }

        return $data;
    }

    public static function attachment(ChatAttachment $attachment): array
    {
        return ['id' => $attachment->id, 'message_id' => $attachment->message_id, 'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type, 'file_size' => $attachment->file_size,
            'is_image' => str_starts_with($attachment->mime_type ?? '', 'image/'),
            'created_at' => $attachment->created_at?->toIso8601String()];
    }

    public static function reactions(ChatMessage $message): array
    {
        return $message->reactions->groupBy('emoji')->map(fn ($items, $emoji) => [
            'emoji' => $emoji, 'count' => $items->count(), 'user_ids' => $items->pluck('user_id')->values()->all(),
        ])->values()->all();
    }

    public static function message(ChatMessage $message): array
    {
        return ['id' => $message->id, 'conversation_id' => $message->conversation_id, 'client_id' => $message->client_id,
            'sender_user_id' => $message->sender_user_id, 'sender' => self::user($message->sender, $message->sender_user_id),
            'body' => $message->body ?? '', 'message_type' => $message->message_type, 'metadata' => $message->metadata ?? [],
            'sent_at' => $message->sent_at?->toIso8601String(), 'revision' => $message->revision,
            'attachments' => $message->chatAttachments->map(fn ($item) => self::attachment($item))->all(),
            'reactions' => self::reactions($message)];
    }

    public static function conversation(ChatConversation $conversation, int $viewerId, bool $details = false): array
    {
        $own = $conversation->chatParticipants->firstWhere('user_id', $viewerId);
        $other = $conversation->chatParticipants->firstWhere('user_id', '!=', $viewerId);
        $partner = $conversation->type === 'direct' && $other ? self::user($other->user, $other->user_id, $details) : null;
        $last = $conversation->lastMessage;
        $data = ['id' => $conversation->id, 'type' => $conversation->type, 'title' => $conversation->title,
            'name' => $conversation->type === 'group' ? $conversation->title : ($partner['name'] ?? 'Hội thoại EduMatch'),
            'avatar_url' => $conversation->type === 'group' ? null : ($partner['avatar_url'] ?? null), 'partner' => $partner,
            'member_count' => $conversation->chatParticipants->count(), 'revision' => $conversation->revision,
            'last_message_id' => $conversation->last_message_id, 'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'preview' => $last ? Str::limit($last->body ?: ($last->message_type === 'image' ? 'Đã gửi ảnh' : ($last->metadata['sticker'] ?? false ? 'Đã gửi nhãn dán' : ($last->metadata['math'] ?? false ? 'Đã gửi công thức' : 'Đã gửi tệp'))), 160) : 'Chưa có tin nhắn',
            'last_sender_user_id' => $last?->sender_user_id, 'unread_count' => (int) ($conversation->unread_count ?? 0),
            'last_read_message_id' => $own?->last_read_message_id ?? 0,
            'read_receipts' => $conversation->chatParticipants->map(fn ($p) => ['user_id' => $p->user_id, 'last_read_message_id' => $p->last_read_message_id])->all()];
        if ($details) {
            $data['notes'] = $own?->notes ?? '';
            $data['participants'] = $conversation->chatParticipants->map(fn ($p) => self::user($p->user, $p->user_id) + ['participant_role' => $p->role])->all();
        }

        return $data;
    }
}
