<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return $user->status === 'active' && (int) $user->id === (int) $id;
}, ['guards' => ['api']]);

Broadcast::channel('online', function ($user) {
    return $user->status === 'active' ? ['id' => $user->id] : false;
}, ['guards' => ['api']]);

Broadcast::channel('chat.conversation.{id}', function ($user, $id) {
    return $user->status === 'active' && \App\Models\ChatParticipant::where('conversation_id', $id)->where('user_id', $user->id)
        ->whereHas('conversation', fn ($q) => $q->where('status', 'active'))->exists();
}, ['guards' => ['api']]);
