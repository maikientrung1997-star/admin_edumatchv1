<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Events\MessageReacted;
use App\Events\MessageRead;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Support\ChatData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\IsolatedMysqlTestCase;

class RealtimeChatTest extends IsolatedMysqlTestCase
{
    private function account(string $role = 'student', array $values = []): User
    {
        $user = User::create($values + ['name' => 'QA '.$role, 'email' => uniqid().'@gmail.com', 'role' => $role, 'password' => 'Password@123']);
        if ($role === 'teacher') {
            $user->teacherProfile()->create(['full_name' => $user->name]);
        } elseif ($role === 'center') {
            $user->center()->create(['name' => 'QA Trung tâm', 'slug' => 'qa-center-'.$user->id]);
        } else {
            $user->studentProfile()->create(['full_name' => $user->name]);
        }

        return $user;
    }

    private function api(User $user, string $method, string $path, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, '/api/chat'.$path, $data, ['Authorization' => 'Bearer '.$user->createToken('chat-test')->plainTextToken]);
    }

    private function direct(User $first, User $second): int
    {
        return $this->api($first, 'POST', '/conversations', ['recipient_id' => $second->id])->assertOk()->json('data.id');
    }

    private function send(User $user, int $conversationId, array $data = [])
    {
        return $this->api($user, 'POST', '/conversations/'.$conversationId.'/messages', $data + ['body' => 'Tin nhắn thực tế', 'client_id' => (string) Str::uuid()]);
    }

    public function test_direct_conversation_is_unique_for_both_directions_and_all_business_role_pairs(): void
    {
        $student = $this->account();
        $teacher = $this->account('teacher');
        $center = $this->account('center');
        $id = $this->direct($student, $teacher);
        $this->assertSame($id, $this->direct($teacher, $student));
        $this->assertSame(2, ChatParticipant::where('conversation_id', $id)->count());
        $this->direct($teacher, $center);
        $this->direct($center, $student);
        $this->direct($student, $this->account());
        $this->assertDatabaseCount('chat_conversations', 4);
        $this->api($student, 'POST', '/conversations', ['recipient_id' => $student->id])->assertUnprocessable();
    }

    public function test_private_apis_reject_guests_outsiders_and_blocked_users(): void
    {
        $student = $this->account();
        $teacher = $this->account('teacher');
        $outsider = $this->account();
        $id = $this->direct($student, $teacher);
        $message = $this->send($student, $id)->assertCreated()->json('data.id');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson('/api/chat/conversations')->assertUnauthorized();
        foreach (['', '/messages', '/media'] as $suffix) {
            $this->api($outsider, 'GET', '/conversations/'.$id.$suffix)->assertNotFound();
        }
        $this->send($outsider, $id)->assertNotFound();
        $this->api($outsider, 'PUT', '/conversations/'.$id.'/notes', ['notes' => 'Not allowed'])->assertNotFound();
        $this->api($outsider, 'POST', '/messages/'.$message.'/reactions', ['emoji' => '👍'])->assertNotFound();
        $teacher->update(['status' => 'blocked']);
        $this->api($teacher, 'GET', '/conversations')->assertForbidden();
        $this->send($student, $id)->assertConflict();
    }

    public function test_text_sending_is_idempotent_and_unread_and_read_watermarks_are_precise(): void
    {
        $student = $this->account();
        $teacher = $this->account('teacher');
        $id = $this->direct($student, $teacher);
        $key = (string) Str::uuid();
        $first = $this->send($student, $id, ['client_id' => $key])->assertCreated()->json('data.id');
        $this->send($student, $id, ['client_id' => $key, 'body' => 'Retried'])->assertCreated()->assertJsonPath('data.id', $first)->assertJsonPath('data.body', 'Tin nhắn thực tế');
        $last = $this->send($student, $id)->assertCreated()->json('data.id');
        $this->api($teacher, 'GET', '/conversations?unread=1')->assertOk()->assertJsonPath('data.0.unread_count', 2)->assertJsonPath('meta.counts.unread_messages', 2);
        $this->api($teacher, 'POST', '/conversations/'.$id.'/read', ['last_message_id' => $first])->assertOk();
        $this->api($teacher, 'GET', '/conversations')->assertJsonPath('data.0.unread_count', 1);
        $this->api($teacher, 'POST', '/conversations/'.$id.'/read', ['last_message_id' => $last])->assertOk();
        $this->api($teacher, 'POST', '/conversations/'.$id.'/read', ['last_message_id' => $first])->assertOk()->assertJsonPath('last_read_message_id', $last);
        $this->api($teacher, 'GET', '/conversations?unread=1')->assertJsonCount(0, 'data')->assertJsonPath('meta.counts.unread', 0);
        $this->assertDatabaseCount('chat_messages', 2);
    }

    public function test_read_cursor_must_belong_to_conversation_and_notes_are_per_viewer_and_never_broadcast(): void
    {
        Event::fake([ChatMessageSent::class, ConversationUpdated::class, MessageRead::class, MessageReacted::class]);
        $one = $this->account();
        $two = $this->account('teacher');
        $id = $this->direct($one, $two);
        $otherId = $this->direct($one, $this->account('center'));
        $message = $this->send($one, $otherId)->assertCreated()->json('data.id');
        $this->api($two, 'POST', '/conversations/'.$id.'/read', ['last_message_id' => $message])->assertUnprocessable();
        $this->api($one, 'PUT', '/conversations/'.$id.'/notes', ['notes' => 'Ghi chú riêng A'])->assertOk();
        $this->api($two, 'PUT', '/conversations/'.$id.'/notes', ['notes' => 'Ghi chú riêng B'])->assertOk();
        $this->api($one, 'GET', '/conversations/'.$id)->assertJsonPath('data.notes', 'Ghi chú riêng A')->assertDontSee('Ghi chú riêng B');
        $this->api($two, 'GET', '/conversations/'.$id)->assertJsonPath('data.notes', 'Ghi chú riêng B')->assertDontSee('Ghi chú riêng A');
        $this->api($one, 'GET', '/conversations')->assertJsonMissingPath('data.0.notes');
        Event::assertNotDispatched(ChatMessageSent::class); // outer test transaction has not committed
    }

    public function test_private_uploads_media_and_downloads_require_conversation_membership(): void
    {
        Storage::fake('chat');
        $one = $this->account();
        $two = $this->account('teacher');
        $id = $this->direct($one, $two);
        $response = $this->send($one, $id, ['body' => null, 'files' => [UploadedFile::fake()->image('lesson.png'), UploadedFile::fake()->createWithContent('notes.txt', 'Actual shared notes')]])->assertCreated();
        $response->assertJsonCount(2, 'data.attachments')->assertJsonMissingPath('data.attachments.0.file_path');
        $attachments = ChatAttachment::all();
        foreach ($attachments as $attachment) {
            Storage::disk('chat')->assertExists($attachment->file_path);
            $this->api($two, 'GET', '/attachments/'.$attachment->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->api($this->account(), 'GET', '/attachments/'.$attachment->id)->assertNotFound();
        }
        $this->api($two, 'GET', '/conversations/'.$id.'/media?type=image')->assertJsonCount(1, 'data')->assertJsonPath('data.0.file_name', 'lesson.png');
        $this->api($two, 'GET', '/conversations/'.$id.'/media?type=file')->assertJsonCount(1, 'data')->assertJsonPath('data.0.file_name', 'notes.txt');
    }

    public function test_empty_invalid_or_oversized_messages_and_files_are_rejected(): void
    {
        Storage::fake('chat');
        $one = $this->account();
        $id = $this->direct($one, $this->account('teacher'));
        $this->send($one, $id, ['body' => null])->assertUnprocessable();
        $this->send($one, $id, ['body' => ['invalid']])->assertUnprocessable();
        $this->send($one, $id, ['body' => str_repeat('A', 4001)])->assertUnprocessable();
        $this->send($one, $id, ['files' => [UploadedFile::fake()->createWithContent('attack.html', '<script>alert(1)</script>')]])->assertUnprocessable();
        $this->send($one, $id, ['files' => [UploadedFile::fake()->create('too-big.pdf', 10241, 'application/pdf')]])->assertUnprocessable();
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertEmpty(Storage::disk('chat')->allFiles());
    }

    public function test_reactions_toggle_and_change_without_duplicates(): void
    {
        $one = $this->account();
        $two = $this->account('teacher');
        $id = $this->direct($one, $two);
        $message = $this->send($one, $id)->assertCreated()->json('data.id');
        $this->api($two, 'POST', '/messages/'.$message.'/reactions', ['emoji' => '👍'])->assertOk()->assertJsonPath('data.reactions.0.count', 1);
        $this->api($two, 'POST', '/messages/'.$message.'/reactions', ['emoji' => '❤️'])->assertOk()->assertJsonPath('data.reactions.0.emoji', '❤️');
        $this->assertDatabaseCount('chat_message_reactions', 1);
        $this->api($two, 'POST', '/messages/'.$message.'/reactions', ['emoji' => '❤️'])->assertOk()->assertJsonCount(0, 'data.reactions');
        $this->api($one, 'POST', '/messages/'.$message.'/reactions', ['emoji' => 'invalid'])->assertUnprocessable();
    }

    public function test_group_membership_search_and_real_profiles_do_not_expose_private_data(): void
    {
        $one = $this->account();
        $two = $this->account('teacher', ['name' => 'Cô giáo thật', 'phone' => '+84981234567']);
        $three = $this->account('center');
        $group = $this->api($one, 'POST', '/groups', ['title' => 'Nhóm học thật', 'member_ids' => [$two->id, $three->id]])->assertCreated()->json('data.id');
        $this->send($three, $group)->assertCreated();
        $this->api($two, 'GET', '/conversations?type=group')->assertJsonPath('data.0.member_count', 3)->assertJsonPath('data.0.name', 'Nhóm học thật');
        $this->api($this->account(), 'GET', '/conversations/'.$group)->assertNotFound();
        $this->api($one, 'GET', '/users/search?q=0981234567')->assertOk()->assertJsonPath('data.0.id', $two->id)->assertJsonPath('data.0.phone', $two->phone)
            ->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.password');
        $this->api($one, 'GET', '/users/search?q=Cô')->assertJsonPath('data.0.phone', null);
        $direct = $this->direct($one, $two);
        $this->api($one, 'GET', '/conversations/'.$direct)->assertOk()->assertJsonPath('data.partner.role', 'teacher')->assertJsonPath('data.partner.phone', null)
            ->assertJsonMissingPath('data.partner.date_of_birth');
    }

    public function test_history_uses_stable_cursors_and_realtime_events_have_private_whitelisted_payloads(): void
    {
        $one = $this->account();
        $id = $this->direct($one, $this->account('teacher'));
        $messages = [];
        for ($i = 0; $i < 35; $i++) {
            $messages[] = ChatMessage::create(['conversation_id' => $id, 'sender_user_id' => $one->id, 'body' => 'Message '.$i, 'sent_at' => now()]);
        }
        $this->api($one, 'GET', '/conversations/'.$id.'/messages')->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('meta.has_more', true)->assertJsonPath('data.0.id', $messages[5]->id);
        $this->api($one, 'GET', '/conversations/'.$id.'/messages?before_id='.$messages[5]->id)->assertJsonCount(5, 'data');
        $this->api($one, 'GET', '/conversations/'.$id.'/messages?after_id='.$messages[29]->id)->assertJsonCount(5, 'data');
        $payload = ChatData::message($messages[0]->load(ChatData::MESSAGE_RELATIONS));
        foreach ([new ChatMessageSent('chat.conversation.'.$id, $payload), new ConversationUpdated('user.'.$one->id, ['conversation_id' => $id]),
            new MessageRead('chat.conversation.'.$id, ['last_read_message_id' => $messages[0]->id]), new MessageReacted('chat.conversation.'.$id, ['emoji' => '👍'])] as $event) {
            $this->assertStringStartsWith('private-', $event->broadcastOn()[0]->name);
            $this->assertStringNotContainsString('password', json_encode($event->broadcastWith()));
            $this->assertStringNotContainsString('notes', json_encode($event->broadcastWith()));
        }
    }

    public function test_private_and_presence_channels_enforce_active_membership(): void
    {
        config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.key' => 'test-key', 'broadcasting.connections.pusher.secret' => 'test-secret', 'broadcasting.connections.pusher.app_id' => '123']);
        require base_path('routes/channels.php');
        $one = $this->account();
        $two = $this->account('teacher');
        $outsider = $this->account();
        $id = $this->direct($one, $two);
        foreach (['private-chat.conversation.'.$id, 'presence-online', 'private-user.'.$one->id] as $channel) {
            $this->app['auth']->forgetGuards();
            $this->postJson('/broadcasting/auth', ['socket_id' => '12.34', 'channel_name' => $channel], ['Authorization' => 'Bearer '.$one->createToken('channel')->plainTextToken])->assertOk();
        }
        $this->app['auth']->forgetGuards();
        $this->postJson('/broadcasting/auth', ['socket_id' => '12.34', 'channel_name' => 'private-chat.conversation.'.$id], ['Authorization' => 'Bearer '.$outsider->createToken('channel')->plainTextToken])->assertForbidden();
        $one->update(['status' => 'blocked']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/broadcasting/auth', ['socket_id' => '12.34', 'channel_name' => 'presence-online'], ['Authorization' => 'Bearer '.$one->createToken('channel')->plainTextToken])->assertForbidden();
    }
}
