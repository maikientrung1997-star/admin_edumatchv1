<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        foreach (['send' => 60, 'create' => 20, 'search' => 60, 'typing' => 30] as $action => $limit) {
            RateLimiter::for('chat-'.$action, fn (Request $request) => Limit::perMinute($limit)->by('chat-'.$action.':'.($request->user()?->id ?? $request->ip())));
        }
        RateLimiter::for('google-challenge', fn (Request $request) => Limit::perMinute(30)->by('google-challenge:'.$request->ip()));
        RateLimiter::for('google-auth', fn (Request $request) => Limit::perMinute(15)->by('google-auth:'.$request->ip()));
        RateLimiter::for('registration-otp-send', function (Request $request) {
            $email = $request->input('email');
            $identity = hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : '');

            return [
                Limit::perMinute(5)->by('registration-send-ip:'.$request->ip()),
                Limit::perMinute(1)->by('registration-send-email:'.$identity),
            ];
        });
        $resetIdentity = fn (Request $request) => hash('sha256', \App\Support\VietnamesePhone::normalize(mb_strtolower(trim((string) (is_string($request->input('identifier')) ? $request->input('identifier') : '')))));
        RateLimiter::for('password-reset-send', function (Request $request) use ($resetIdentity) {
            return [
                Limit::perMinute(5)->by('otp-send-ip:'.$request->ip()),
                Limit::perMinute(1)->by('otp-send:'.$resetIdentity($request)),
                Limit::perHour(5)->by('otp-send-hour:'.$resetIdentity($request)),
            ];
        });
        RateLimiter::for('password-reset-verify', function (Request $request) use ($resetIdentity) {
            return [Limit::perMinute(10)->by('otp-verify:'.$request->ip().':'.$resetIdentity($request))];
        });
        RateLimiter::for('api', function (Request $request) {
            if ($request->is('api/chat/*')) {
                return Limit::perMinute(240)->by('chat-api:'.($request->user()?->id ?? $request->ip()));
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
