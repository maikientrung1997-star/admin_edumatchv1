<?php

return [
    // Enable only when Client Events is enabled in the Pusher dashboard.
    // Otherwise typing uses an authenticated, user-triggered POST and Pusher.
    'client_typing' => env('CHAT_CLIENT_TYPING', false),
];
