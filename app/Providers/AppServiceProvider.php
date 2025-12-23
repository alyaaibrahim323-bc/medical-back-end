<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\TherapySession;
use App\Observers\TherapySessionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

   
    public function boot(): void
    {
        User::observe(UserObserver::class);
        TherapySession::observe(TherapySessionObserver::class);
        RateLimiter::for('api', function (Request $request) {
                    return Limit::perMinute(60)->by(
                        optional($request->user())->id ?: $request->ip()
                    );
        });

    }
}
