<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The React client authenticates private channels with its Sanctum bearer
        // token through the API guard. Do not use the web/session middleware here.
        Broadcast::routes(['middleware' => ['api', 'auth:api', \App\Http\Middleware\EnsureActiveAccount::class]]);

        require base_path('routes/channels.php');
    }
}
