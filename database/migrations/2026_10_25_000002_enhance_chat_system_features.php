<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('chat_revision')->default(0));
        DB::statement('ALTER TABLE chat_conversations DROP CHECK chat_conversations_recipient_check');
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('student_user_id')->nullable()->change();
            $table->string('type', 20)->default('direct');
            $table->string('title', 150)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->string('direct_key', 64)->nullable()->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->index(['type', 'last_message_at', 'id']);
        });
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->text('notes')->nullable();
            $table->string('role', 20)->default('member');
            $table->unsignedBigInteger('last_read_message_id')->default(0);
        });
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->json('metadata')->nullable();
            $table->uuid('client_id')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->unique(['conversation_id', 'sender_user_id', 'client_id'], 'chat_message_client_unique');
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 20);
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
            $table->index(['message_id', 'emoji']);
        });
        DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conversations_type_check CHECK (type IN ('direct', 'group'))");
        DB::table('chat_conversations')->orderBy('id')->chunkById(100, function ($conversations) {
            foreach ($conversations as $conversation) {
                $ids = DB::table('chat_participants')->where('conversation_id', $conversation->id)->orderBy('user_id')->pluck('user_id');
                $key = $ids->count() === 2 ? $ids->join(':') : null;
                DB::table('chat_conversations')->where('id', $conversation->id)->update([
                    'direct_key' => $key && ! DB::table('chat_conversations')->where('direct_key', $key)->exists() ? $key : null,
                    'last_message_id' => DB::table('chat_messages')->where('conversation_id', $conversation->id)->whereNull('deleted_at')->max('id'),
                ]);
                foreach (DB::table('chat_participants')->where('conversation_id', $conversation->id)->whereNotNull('last_read_at')->get() as $participant) {
                    DB::table('chat_participants')->where('id', $participant->id)->update(['last_read_message_id' => DB::table('chat_messages')->where('conversation_id', $conversation->id)->where('sent_at', '<=', $participant->last_read_at)->max('id') ?? 0]);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::table('chat_conversations')->whereNull('student_user_id')->orWhere('type', 'group')
            ->orWhereRaw('(teacher_user_id IS NOT NULL) + (center_id IS NOT NULL) != 1')->exists()) {
            throw new RuntimeException('Cannot downgrade chat while newer direct/group conversations require the new schema.');
        }
        Schema::dropIfExists('chat_message_reactions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('chat_revision'));
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropUnique('chat_message_client_unique');
            $table->dropIndex(['conversation_id', 'id']);
            $table->dropColumn(['metadata', 'client_id', 'revision']);
        });
        Schema::table('chat_participants', fn (Blueprint $table) => $table->dropColumn(['notes', 'role', 'last_read_message_id']));
        DB::statement('ALTER TABLE chat_conversations DROP CHECK chat_conversations_type_check');
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropUnique(['direct_key']);
            $table->dropIndex(['type', 'last_message_at', 'id']);
            $table->dropColumn(['type', 'title', 'avatar_path', 'direct_key', 'created_by_user_id', 'last_message_id', 'revision']);
            $table->unsignedBigInteger('student_user_id')->nullable(false)->change();
        });
        DB::statement('ALTER TABLE chat_conversations ADD CONSTRAINT chat_conversations_recipient_check CHECK ((teacher_user_id IS NOT NULL) + (center_id IS NOT NULL) = 1)');
    }
};
