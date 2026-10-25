<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Events\MessageReacted;
use App\Events\MessageRead;
use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Support\ChatData;
use App\Support\VietnamesePhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    public const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡'];

    public function authorize(User $user, ChatConversation $conversation): ChatParticipant
    {
        abort_unless($user->status === 'active' && $conversation->status === 'active', 403);

        return $conversation->chatParticipants()->where('user_id', $user->id)->firstOrFail();
    }

    private function unread(Builder $query, int $userId): void
    {
        $query->where('sender_user_id', '!=', $userId)
            ->whereRaw('chat_messages.id > COALESCE((SELECT p.last_read_message_id FROM chat_participants p WHERE p.conversation_id = chat_messages.conversation_id AND p.user_id = ? LIMIT 1), 0)', [$userId]);
    }

    private function base(User $user): Builder
    {
        return ChatConversation::where('status', 'active')->whereHas('chatParticipants', fn ($q) => $q->where('user_id', $user->id));
    }

    private function presentation(User $user): Builder
    {
        return $this->base($user)->with(ChatData::CONVERSATION_RELATIONS)
            ->withCount(['chatMessages as unread_count' => fn ($q) => $this->unread($q, $user->id)]);
    }

    public function counts(User $user): array
    {
        $unread = ChatMessage::whereHas('conversation', fn ($q) => $q->where('status', 'active')
            ->whereHas('chatParticipants', fn ($q) => $q->where('user_id', $user->id)));
        $this->unread($unread, $user->id);

        return ['total' => $this->base($user)->count(), 'unread' => $unread->clone()->distinct()->count('conversation_id'), 'unread_messages' => $unread->count()];
    }

    public function conversations(User $user, array $filters): array
    {
        return DB::transaction(function () use ($user, $filters) {
            $revision = (int) DB::table('users')->where('id', $user->id)->value('chat_revision');
            $query = $this->presentation($user);
            if (! empty($filters['unread'])) {
                $query->whereHas('chatMessages', fn ($q) => $this->unread($q, $user->id));
            }
            if (in_array($filters['type'] ?? null, ['direct', 'group'], true)) {
                $query->where('type', $filters['type']);
            }
            if ($search = $filters['q'] ?? null) {
                $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('participants', fn ($q) => $q->where('users.id', '!=', $user->id)
                        ->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhereIn('phone', VietnamesePhone::variants($search)))));
            }
            $page = $query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(30);

            return ['data' => $page->getCollection()->map(fn ($c) => ChatData::conversation($c, $user->id))->all(),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                    'counts' => $this->counts($user), 'revision' => $revision]];
        });
    }

    public function detail(User $user, ChatConversation $conversation): array
    {
        $this->authorize($user, $conversation);
        $conversation = $this->presentation($user)->findOrFail($conversation->id);
        $conversation->load(['chatParticipants.user.studentProfile.province', 'chatParticipants.user.studentProfile.district', 'chatParticipants.user.studentProfile.ward',
            'chatParticipants.user.teacherProfile.province', 'chatParticipants.user.teacherProfile.district', 'chatParticipants.user.teacherProfile.ward',
            'chatParticipants.user.center.province', 'chatParticipants.user.center.district', 'chatParticipants.user.center.ward',
            'chatParticipants.user.studentLearningNeeds.subject', 'chatParticipants.user.studentLearningNeeds.level']);

        return ChatData::conversation($conversation, $user->id, true);
    }

    public function searchUsers(User $user, string $search): array
    {
        $users = User::with(['center', 'teacherProfile'])->where('status', 'active')->whereIn('role', ['student', 'teacher', 'center'])->where('id', '!=', $user->id)
            ->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')
                ->orWhereIn('phone', VietnamesePhone::variants($search))->orWhere('email', mb_strtolower($search))
                ->orWhereHas('center', fn ($q) => $q->where('name', 'like', '%'.$search.'%')))
            ->orderBy('name')->limit(20)->get();
        $keys = $users->map(fn ($other) => $this->directKey($user->id, $other->id));
        $existing = $this->base($user)->whereIn('direct_key', $keys)->pluck('id', 'direct_key');

        return $users->map(function ($other) use ($user, $search, $existing) {
            $data = ChatData::user($other);
            $data['phone'] = in_array($other->phone, VietnamesePhone::variants($search), true) ? $other->phone
                : ($other->role === 'center' ? $other->center?->hotline : ($other->teacherProfile?->show_phone ? $other->phone : null));
            $data['conversation_id'] = $existing[$this->directKey($user->id, $other->id)] ?? null;

            return $data;
        })->all();
    }

    private function directKey(int $first, int $second): string
    {
        return min($first, $second).':'.max($first, $second);
    }

    public function openDirect(User $user, int $recipientId): ChatConversation
    {
        abort_if($user->id === $recipientId, 422, 'Không thể tạo hội thoại với chính mình.');

        return DB::transaction(function () use ($user, $recipientId) {
            // Serialize creation for this pair, including opposite-direction requests.
            $members = User::whereIn('id', [$user->id, $recipientId])->orderBy('id')->lockForUpdate()->get();
            abort_unless($members->count() === 2 && $members->every(fn ($u) => $u->status === 'active'), 404, 'Tài khoản không còn hoạt động.');
            $key = $this->directKey($user->id, $recipientId);
            $conversation = ChatConversation::where('direct_key', $key)->first();
            if ($conversation) {
                $this->authorize($user, $conversation);

                return $conversation;
            }
            $conversation = ChatConversation::create(['type' => 'direct', 'direct_key' => $key, 'created_by_user_id' => $user->id,
                'student_user_id' => $members->firstWhere('role', 'student')?->id,
                'teacher_user_id' => $members->firstWhere('role', 'teacher')?->id,
                'center_id' => $members->firstWhere('role', 'center')?->center?->id]);
            foreach ($members as $member) {
                $conversation->chatParticipants()->create(['user_id' => $member->id, 'joined_at' => now()]);
            }
            $this->inboxEvents($conversation, $members);

            return $conversation;
        }, 3);
    }

    public function createGroup(User $user, string $title, array $memberIds): ChatConversation
    {
        $ids = array_unique([$user->id, ...$memberIds]);

        return DB::transaction(function () use ($user, $title, $ids) {
            $members = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless(count($ids) >= 2 && $members->count() === count($ids) && $members->every(fn ($u) => $u->status === 'active'), 422, 'Thành viên nhóm không hợp lệ.');
            $conversation = ChatConversation::create(['type' => 'group', 'title' => $title, 'created_by_user_id' => $user->id]);
            foreach ($members as $member) {
                $conversation->chatParticipants()->create(['user_id' => $member->id, 'joined_at' => now(), 'role' => $member->id === $user->id ? 'admin' : 'member']);
            }
            $this->inboxEvents($conversation, $members);

            return $conversation;
        }, 3);
    }

    private function lockConversation(User $user, ChatConversation $conversation): array
    {
        $conversation = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
        abort_unless($conversation->status === 'active', 403);
        $participants = $conversation->chatParticipants()->orderBy('user_id')->lockForUpdate()->get();
        abort_unless($participants->contains('user_id', $user->id), 404);
        $members = User::whereIn('id', $participants->pluck('user_id'))->orderBy('id')->lockForUpdate()->get();

        return [$conversation, $participants, $members];
    }

    private function publish(object $event): bool
    {
        try {
            event($event);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Chat realtime publish failed; message remains persisted.', ['event' => get_class($event), 'exception' => get_class($exception)]);

            return false;
        }
    }

    private function inboxEvents(ChatConversation $conversation, $members): void
    {
        $conversation->increment('revision');
        User::whereIn('id', $members->pluck('id'))->increment('chat_revision');
        $conversation->load(ChatData::CONVERSATION_RELATIONS);
        $unread = DB::table('chat_messages as m')->join('chat_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('m.conversation_id', $conversation->id)->whereNull('m.deleted_at')->whereColumn('m.id', '>', 'p.last_read_message_id')
            ->whereColumn('m.sender_user_id', '!=', 'p.user_id')->groupBy('p.user_id')->selectRaw('p.user_id, COUNT(*) AS amount')->pluck('amount', 'user_id');
        $counts = DB::table('chat_participants as p')->join('chat_conversations as c', 'c.id', '=', 'p.conversation_id')
            ->leftJoin('chat_messages as m', function ($join) {
                $join->on('m.conversation_id', '=', 'p.conversation_id')->on('m.id', '>', 'p.last_read_message_id')
                    ->whereColumn('m.sender_user_id', '!=', 'p.user_id')->whereNull('m.deleted_at');
            })->whereIn('p.user_id', $members->pluck('id'))->where('c.status', 'active')->groupBy('p.user_id')
            ->selectRaw('p.user_id, COUNT(DISTINCT c.id) AS total, COUNT(DISTINCT CASE WHEN m.id IS NOT NULL THEN c.id END) AS unread, COUNT(m.id) AS unread_messages')
            ->get()->keyBy('user_id');
        foreach ($members->where('status', 'active') as $member) {
            $conversation->setAttribute('unread_count', (int) ($unread[$member->id] ?? 0));
            $totals = $counts[$member->id];
            $event = new ConversationUpdated('user.'.$member->id, [
                'conversation' => ChatData::conversation($conversation, $member->id),
                'counts' => ['total' => (int) $totals->total, 'unread' => (int) $totals->unread, 'unread_messages' => (int) $totals->unread_messages],
                'revision' => (int) $member->getAttribute('chat_revision') + 1,
            ]);
            DB::afterCommit(fn () => $this->publish($event));
        }
    }

    public function send(User $user, ChatConversation $conversation, array $data, array $files): array
    {
        $paths = [];
        $storedPaths = [];
        $published = true;
        try {
            $message = DB::transaction(function () use ($user, $conversation, $data, $files, &$paths, &$storedPaths, &$published) {
                [$conversation, $participants, $members] = $this->lockConversation($user, $conversation);
                $existing = $conversation->chatMessages()->where('sender_user_id', $user->id)->where('client_id', $data['client_id'])->first();
                if ($existing) {
                    return $existing->load(ChatData::MESSAGE_RELATIONS);
                }
                abort_unless($members->count() === $participants->count() && $members->every(fn ($member) => $member->status === 'active'), 409, 'Có thành viên không còn hoạt động. Không thể gửi tin nhắn.');
                $metadata = array_intersect_key($data['metadata'] ?? [], array_flip(['math', 'formula', 'reply_to_id', 'sticker']));
                if (! empty($metadata['reply_to_id'])) {
                    abort_unless($conversation->chatMessages()->whereKey($metadata['reply_to_id'])->exists(), 422, 'Tin nhắn trích dẫn không thuộc hội thoại.');
                }
                $message = $conversation->chatMessages()->create(['sender_user_id' => $user->id, 'body' => $data['body'] ?? null,
                    'message_type' => $files ? (collect($files)->every(fn ($file) => str_starts_with($file->getMimeType(), 'image/')) ? 'image' : 'file') : 'text',
                    'metadata' => $metadata ?: null, 'client_id' => $data['client_id'], 'sent_at' => now()]);
                foreach ($files as $index => $file) {
                    if (! isset($storedPaths[$index])) {
                        $storedPaths[$index] = $file->store((string) $conversation->id, 'chat');
                        $paths[] = $storedPaths[$index];
                    }
                    $path = $storedPaths[$index];
                    $message->chatAttachments()->create(['file_path' => $path,
                        'file_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                        'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize()]);
                }
                $conversation->update(['last_message_id' => $message->id, 'last_message_at' => $message->sent_at]);
                $own = $participants->firstWhere('user_id', $user->id);
                $own->update(['last_read_message_id' => $message->id, 'last_read_at' => now()]);
                $message->load(ChatData::MESSAGE_RELATIONS);
                $payload = ChatData::message($message);
                if (strlen(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > 8500) {
                    $payload = ['id' => $message->id, 'conversation_id' => $conversation->id, 'sender_user_id' => $user->id, 'requires_fetch' => true];
                }
                DB::afterCommit(function () use ($conversation, $payload, &$published) {
                    $published = $this->publish(new ChatMessageSent('chat.conversation.'.$conversation->id, $payload));
                });
                $this->inboxEvents($conversation, $members);

                return $message;
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('chat')->delete($path);
            }
            throw $exception;
        }

        foreach ($paths as $path) {
            if (! $message->chatAttachments->contains('file_path', $path)) {
                Storage::disk('chat')->delete($path);
            }
        }

        return ['data' => ChatData::message($message), 'realtime_published' => $published];
    }

    public function history(User $user, ChatConversation $conversation, array $filters): array
    {
        $this->authorize($user, $conversation);
        $after = (int) ($filters['after_id'] ?? 0);
        $query = $conversation->chatMessages()->with(ChatData::MESSAGE_RELATIONS);
        if ($after) {
            $query->where('id', '>', $after)->orderBy('id');
        } else {
            if ($before = $filters['before_id'] ?? null) {
                $query->where('id', '<', $before);
            }
            $query->orderByDesc('id');
        }
        $messages = $query->limit(31)->get();
        $hasMore = $messages->count() > 30;
        $messages = $messages->take(30)->sortBy('id')->values();

        return ['data' => $messages->map(fn ($m) => ChatData::message($m))->all(), 'meta' => ['has_more' => $hasMore,
            'oldest_id' => $messages->first()?->id, 'latest_id' => $messages->last()?->id]];
    }

    public function read(User $user, ChatConversation $conversation, int $lastId): array
    {
        return DB::transaction(function () use ($user, $conversation, $lastId) {
            [$conversation, $participants, $members] = $this->lockConversation($user, $conversation);
            $own = $participants->firstWhere('user_id', $user->id);
            abort_unless($conversation->chatMessages()->whereKey($lastId)->exists(), 422, 'Mốc đọc không thuộc hội thoại.');
            if ($lastId > $own->last_read_message_id) {
                $own->update(['last_read_message_id' => $lastId, 'last_read_at' => now()]);
                $event = new MessageRead('chat.conversation.'.$conversation->id, ['conversation_id' => $conversation->id,
                    'read_by_user_id' => $user->id, 'last_read_message_id' => $lastId, 'read_at' => $own->last_read_at->toIso8601String()]);
                DB::afterCommit(fn () => $this->publish($event));
                $this->inboxEvents($conversation, $members);
            }

            return ['last_read_message_id' => $own->last_read_message_id];
        }, 3);
    }

    public function react(User $user, ChatMessage $message, string $emoji): array
    {
        return DB::transaction(function () use ($user, $message, $emoji) {
            $conversation = $message->conversation;
            $this->lockConversation($user, $conversation);
            $message = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $reaction = ChatMessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->first();
            if ($reaction?->emoji === $emoji) {
                $reaction->delete();
            } else {
                ChatMessageReaction::updateOrCreate(['message_id' => $message->id, 'user_id' => $user->id], ['emoji' => $emoji]);
            }
            $message->increment('revision');
            $message->load('reactions');
            $payload = ['conversation_id' => $message->conversation_id, 'message_id' => $message->id,
                'user_id' => $user->id, 'revision' => $message->revision, 'reactions' => ChatData::reactions($message)];
            DB::afterCommit(fn () => $this->publish(new MessageReacted('chat.conversation.'.$conversation->id, $payload)));

            return $payload;
        }, 3);
    }

    public function saveNote(User $user, ChatConversation $conversation, ?string $notes): void
    {
        $this->authorize($user, $conversation)->update(['notes' => $notes]);
    }

    public function media(User $user, ChatConversation $conversation, array $filters): array
    {
        $this->authorize($user, $conversation);
        $query = ChatAttachment::whereHas('message', fn ($q) => $q->where('conversation_id', $conversation->id));
        if ($type = $filters['type'] ?? null) {
            $query->where('mime_type', $type === 'image' ? 'like' : 'not like', 'image/%');
        }
        if ($before = $filters['before_id'] ?? null) {
            $query->where('id', '<', $before);
        }
        $items = $query->orderByDesc('id')->limit(31)->get();

        return ['data' => $items->take(30)->map(fn ($a) => ChatData::attachment($a))->all(),
            'meta' => ['has_more' => $items->count() > 30, 'before_id' => $items->take(30)->last()?->id]];
    }
}
