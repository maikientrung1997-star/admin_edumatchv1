<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatService;
use App\Support\ChatData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function config()
    {
        return response()->json(['client_typing' => (bool) config('chat.client_typing'), 'max_file_bytes' => 10485760, 'max_files' => 3, 'emojis' => ChatService::EMOJIS]);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(['direct', 'group'])],
            'unread' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1']]);

        return response()->json($this->chat->conversations($request->user(), $data));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['recipient_id' => ['required', 'integer', 'min:1']]);
        $conversation = $this->chat->openDirect($request->user(), $data['recipient_id']);

        return response()->json(['data' => $this->chat->detail($request->user(), $conversation)]);
    }

    public function group(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'member_ids' => ['required', 'array', 'min:1', 'max:19'],
            'member_ids.*' => ['required', 'integer', 'min:1', 'distinct']]);
        $conversation = $this->chat->createGroup($request->user(), $data['title'], $data['member_ids']);

        return response()->json(['data' => $this->chat->detail($request->user(), $conversation)], 201);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        return response()->json(['data' => $this->chat->detail($request->user(), $conversation)]);
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $data = $request->validate(['before_id' => ['nullable', 'integer', 'min:1', 'prohibits:after_id'], 'after_id' => ['nullable', 'integer', 'min:1']]);

        return response()->json($this->chat->history($request->user(), $conversation, $data));
    }

    public function message(Request $request, ChatMessage $message)
    {
        $this->chat->authorize($request->user(), $message->conversation);

        return response()->json(['data' => ChatData::message($message->load(ChatData::MESSAGE_RELATIONS))]);
    }

    public function send(SendChatMessageRequest $request, ChatConversation $conversation)
    {
        return response()->json($this->chat->send($request->user(), $conversation, $request->validated(), $request->file('files', [])), 201);
    }

    public function read(Request $request, ChatConversation $conversation)
    {
        $data = $request->validate(['last_message_id' => ['required', 'integer', 'min:1']]);

        return response()->json($this->chat->read($request->user(), $conversation, $data['last_message_id']));
    }

    public function react(Request $request, ChatMessage $message)
    {
        $data = $request->validate(['emoji' => ['required', Rule::in(ChatService::EMOJIS)]]);

        return response()->json(['data' => $this->chat->react($request->user(), $message, $data['emoji'])]);
    }

    public function notes(Request $request, ChatConversation $conversation)
    {
        $data = $request->validate(['notes' => ['present', 'nullable', 'string', 'max:10000']]);
        $this->chat->saveNote($request->user(), $conversation, $data['notes']);

        return response()->json(['notes' => $data['notes'] ?? '']);
    }

    public function media(Request $request, ChatConversation $conversation)
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['image', 'file'])], 'before_id' => ['nullable', 'integer', 'min:1']]);

        return response()->json($this->chat->media($request->user(), $conversation, $data));
    }

    public function attachment(Request $request, ChatAttachment $attachment)
    {
        abort_unless($attachment->message, 404);
        $this->chat->authorize($request->user(), $attachment->message->conversation);
        $disk = Storage::disk('chat');
        abort_unless($disk->exists($attachment->file_path), 404);
        $inline = $request->boolean('inline') && str_starts_with($attachment->mime_type ?? '', 'image/');

        return response()->file($disk->path($attachment->file_path), [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="attachment-'.(int) $attachment->id.'.'.pathinfo($attachment->file_name, PATHINFO_EXTENSION).'"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:150']]);

        return response()->json(['data' => $this->chat->searchUsers($request->user(), $data['q'])]);
    }

    public function typing(Request $request, ChatConversation $conversation)
    {
        $this->chat->authorize($request->user(), $conversation);
        try {
            event(new MessageTyping('chat.conversation.'.$conversation->id, ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id, 'expires_at' => now()->addSeconds(5)->toIso8601String()]));
        } catch (\Throwable $exception) {
            Log::warning('Typing publish failed.', ['exception' => get_class($exception)]);
        }

        return response()->noContent();
    }
}
